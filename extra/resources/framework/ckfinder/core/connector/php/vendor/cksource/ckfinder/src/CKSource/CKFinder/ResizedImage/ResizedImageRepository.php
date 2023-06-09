<?php

/*
 * CKFinder
 * ========
 * https://ckeditor.com/ckfinder/
 * Copyright (c) 2007-2021, CKSource - Frederico Knabben. All rights reserved.
 *
 * The software, this file and its contents are subject to the CKFinder
 * License. Please read the license.txt file before using, installing, copying,
 * modifying or distribute this file or part of its contents. The contents of
 * this file is part of the Source Code of CKFinder.
 */

namespace CKSource\CKFinder\ResizedImage;

use CKSource\CKFinder\Acl\Acl;
use CKSource\CKFinder\Acl\Permission;
use CKSource\CKFinder\CKFinder;
use CKSource\CKFinder\Config;
use CKSource\CKFinder\Event\CKFinderEvent;
use CKSource\CKFinder\Event\ResizeImageEvent;
use CKSource\CKFinder\Exception\FileNotFoundException;
use CKSource\CKFinder\Exception\UnauthorizedException;
use CKSource\CKFinder\Filesystem\Path;
use CKSource\CKFinder\ResourceType\ResourceType;

/**
 * The Thumbnailresources class.
 *
 * A class responsible for resized image management that simplifies
 * operations on resized versions of the image file, like batch renaming/moving
 * together with the original file.
 */
class ResizedImageresources
{
    /**
     * @var CKFinder
     */
    protected $app;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Acl
     */
    protected $acl;

    /**
     * Event dispatcher.
     *
     * @var
     */
    protected $dispatcher;

    public function __construct(CKFinder $app)
    {
        $this->config = $app['config'];
        $this->acl = $app['acl'];
        $this->dispatcher = $app['dispatcher'];
        $this->app = $app;
    }

    /**
     * Returns a resized image for the provided source file.
     *
     * If an appropriate resized version already exists, it is reused.
     *
     * @param string $sourceFileDir
     * @param string $sourceFileName
     * @param int    $requestedWidth
     * @param int    $requestedHeight
     *
     * @throws \Exception
     *
     * @return ResizedImage
     */
    public function getResizedImage(ResourceType $sourceFileResourceType, $sourceFileDir, $sourceFileName, $requestedWidth, $requestedHeight)
    {
        $resizedImage = new ResizedImage(
            $this,
            $sourceFileResourceType,
            $sourceFileDir,
            $sourceFileName,
            $requestedWidth,
            $requestedHeight
        );

        if (!$this->acl->isAllowed($sourceFileResourceType->getName(), $sourceFileDir, Permission::IMAGE_RESIZE_CUSTOM) &&
            !$this->isSizeAllowedInConfig($requestedWidth, $requestedHeight)) {
            throw new UnauthorizedException('Provided size is not allowed in images.sizes configuration');
        }

        if (!$resizedImage->exists() && $resizedImage->requestedSizeIsValid()) {
            $resizedImage->create();

            $resizeImageEvent = new ResizeImageEvent($this->app, $resizedImage);
            $this->dispatcher->dispatch($resizeImageEvent, CKFinderEvent::CREATE_RESIZED_IMAGE);

            if (!$resizeImageEvent->isPropagationStopped()) {
                $resizedImage = $resizeImageEvent->getResizedImage();
                $resizedImage->save();
            }
        }

        return $resizedImage;
    }

    /**
     * Returns an existing resized image.
     *
     * @param string $sourceFileDir
     * @param string $sourceFileName
     * @param string $thumbnailFileName
     *
     * @throws FileNotFoundException
     *
     * @return ResizedImage
     */
    public function getExistingResizedImage(ResourceType $sourceFileResourceType, $sourceFileDir, $sourceFileName, $thumbnailFileName)
    {
        $size = ResizedImage::getSizeFromFilename($thumbnailFileName);

        $resizedImage = new ResizedImage(
            $this,
            $sourceFileResourceType,
            $sourceFileDir,
            $sourceFileName,
            $size['width'],
            $size['height'],
            true
        );

        if (!$resizedImage->exists()) {
            throw new FileNotFoundException('Resized image not found');
        }

        $resizedImage->load();

        return $resizedImage;
    }

    /**
     * @return CKFinder
     */
    public function getContainer()
    {
        return $this->app;
    }

    /**
     * Deletes all resized images for a given file.
     *
     * @param string $sourceFilePath
     * @param string $sourceFileName
     *
     * @return bool `true` if deleted
     */
    public function deleteResizedImages(ResourceType $sourceFileResourceType, $sourceFilePath, $sourceFileName)
    {
        $resizedImagesPath = Path::combine($sourceFileResourceType->getDirectory(), $sourceFilePath, ResizedImage::DIR, $sourceFileName);

        $backend = $sourceFileResourceType->getBackend();

        if ($backend->hasDirectory($resizedImagesPath)) {
            return $backend->deleteDir($resizedImagesPath);
        }

        return false;
    }

    /**
     * Copies all resized images for a given file.
     *
     * @param string $sourceFilePath
     * @param string $sourceFileName
     * @param string $targetFilePath
     * @param string $targetFileName
     */
    public function copyResizedImages(
        ResourceType $sourceFileResourceType,
        $sourceFilePath,
        $sourceFileName,
        ResourceType $targetFileResourceType,
        $targetFilePath,
        $targetFileName
    ) {
        $sourceResizedImagesPath = Path::combine($sourceFileResourceType->getDirectory(), $sourceFilePath, ResizedImage::DIR, $sourceFileName);
        $targetResizedImagesPath = Path::combine($targetFileResourceType->getDirectory(), $targetFilePath, ResizedImage::DIR, $targetFileName);

        $sourceBackend = $sourceFileResourceType->getBackend();
        $targetBackend = $targetFileResourceType->getBackend();

        if ($sourceBackend->hasDirectory($sourceResizedImagesPath)) {
            $resizedImages = $sourceBackend->listContents($sourceResizedImagesPath);

            foreach ($resizedImages as $resizedImage) {
                if (!isset($resizedImage['path'])) {
                    continue;
                }

                $resizedImageStream = $sourceBackend->readStream($resizedImage['path']);

                $sourceImageSize = ResizedImage::getSizeFromFilename($resizedImage['basename']);
                $targetImageFilename = ResizedImage::createFilename($targetFileName, $sourceImageSize['width'], $sourceImageSize['height']);

                $targetBackend->putStream(Path::combine($targetResizedImagesPath, $targetImageFilename), $resizedImageStream);
            }
        }
    }

    /**
     * Renames all resized images created for a given file.
     *
     * @param string $sourceFilePath
     * @param string $originalSourceFileName
     * @param string $newSourceFileName
     */
    public function renameResizedImages(ResourceType $sourceFileResourceType, $sourceFilePath, $originalSourceFileName, $newSourceFileName)
    {
        $resizedImagesDir = Path::combine($sourceFileResourceType->getDirectory(), $sourceFilePath, ResizedImage::DIR);
        $resizedImagesPath = Path::combine($resizedImagesDir, $originalSourceFileName);
        $newResizedImagesPath = Path::combine($resizedImagesDir, $newSourceFileName);

        $backend = $sourceFileResourceType->getBackend();

        if ($backend->hasDirectory($resizedImagesPath)) {
            if ($backend->rename($resizedImagesPath, $newResizedImagesPath)) {
                $resizedImages = $backend->listContents($newResizedImagesPath);

                foreach ($resizedImages as $resizedImage) {
                    if (!isset($resizedImage['path'])) {
                        continue;
                    }

                    $sourceImageSize = ResizedImage::getSizeFromFilename($resizedImage['basename']);
                    $newResizedImageFilename = ResizedImage::createFilename($newSourceFileName, $sourceImageSize['width'], $sourceImageSize['height']);

                    $backend->rename($resizedImage['path'], Path::combine($newResizedImagesPath, $newResizedImageFilename));
                }
            }
        }
    }

    /**
     * Returns a list of resized images generated for a given file.
     *
     * @param ResourceType $sourceFileResourceType source file resource type
     * @param string       $sourceFilePath         source file backend-relative path
     * @param string       $sourceFileName         source file name
     * @param array        $filterSizes            array containing names of sizes defined
     *                                             in the `images.sizes` configuration
     *
     * @return array
     */
    public function getResizedImagesList(ResourceType $sourceFileResourceType, $sourceFilePath, $sourceFileName, $filterSizes = [])
    {
        $resizedImagesPath = Path::combine($sourceFileResourceType->getDirectory(), $sourceFilePath, ResizedImage::DIR, $sourceFileName);

        $backend = $sourceFileResourceType->getBackend();

        $resizedImages = [];

        if (!$backend->hasDirectory($resizedImagesPath)) {
            return $resizedImages;
        }

        $resizedImagesFiles = array_filter(
            $backend->listContents($resizedImagesPath),
            function ($v) {
                return isset($v['type']) && 'file' === $v['type'];
            }
        );

        foreach ($resizedImagesFiles as $resizedImage) {
            $size = ResizedImage::getSizeFromFilename($resizedImage['basename']);

            if ($sizeName = $this->getSizeNameFromConfig($size['width'], $size['height'])) {
                if (empty($filterSizes) || \in_array($sizeName, $filterSizes, true)) {
                    $resizedImages[$sizeName] = $this->createNodeValue($resizedImage);
                }

                continue;
            }

            if (empty($filterSizes)) {
                if (!isset($resizedImages['__custom'])) {
                    $resizedImages['__custom'] = [];
                }

                $resizedImages['__custom'][] = $this->createNodeValue($resizedImage);
            }
        }

        return $resizedImages;
    }

    /**
     * @param string $sourceFilePath
     * @param string $sourceFileName
     * @param int    $width
     * @param int    $height
     *
     * @return null|ResizedImage
     */
    public function getResizedImageBySize(ResourceType $sourceFileResourceType, $sourceFilePath, $sourceFileName, $width, $height)
    {
        $resizedImagesPath = Path::combine($sourceFileResourceType->getDirectory(), $sourceFilePath, ResizedImage::DIR, $sourceFileName);

        $backend = $sourceFileResourceType->getBackend();

        if (!$backend->hasDirectory($resizedImagesPath)) {
            return null;
        }

        $resizedImagesFiles = array_filter(
            $backend->listContents($resizedImagesPath),
            function ($v) {
                return isset($v['type']) && 'file' === $v['type'];
            }
        );

        $thresholdPixels = $this->config->get('images.threshold.pixels');
        $thresholdPercent = (float) $this->config->get('images.threshold.percent') / 100;

        foreach ($resizedImagesFiles as $resizedImage) {
            $resizedImageSize = ResizedImage::getSizeFromFilename($resizedImage['basename']);
            $resizedImageWidth = $resizedImageSize['width'];
            $resizedImageHeight = $resizedImageSize['height'];
            if ($resizedImageWidth >= $width && ($resizedImageWidth <= $width + $thresholdPixels || $resizedImageWidth <= $width + $width * $thresholdPercent)
                && $resizedImageHeight >= $height && ($resizedImageHeight <= $height + $thresholdPixels || $resizedImageHeight <= $height + $height * $thresholdPercent)) {
                $resizedImage = new ResizedImage(
                    $this,
                    $sourceFileResourceType,
                    $sourceFilePath,
                    $sourceFileName,
                    $resizedImageWidth,
                    $resizedImageHeight
                );

                if ($resizedImage->exists()) {
                    $resizedImage->load();

                    return $resizedImage;
                }
            }
        }

        return null;
    }

    /**
     * Checks if the provided image size is allowed in the configuration.
     *
     * This is checked when `Permission::IMAGE_RESIZE_CUSTOM`
     * is not allowed in the source file folder.
     *
     * @param int $width
     * @param int $height
     *
     * @return bool `true` if the provided size is allowed in the configuration
     */
    protected function isSizeAllowedInConfig($width, $height)
    {
        $configSizes = $this->config->get('images.sizes');

        foreach ($configSizes as $size) {
            if ($size['width'] === $width && $size['height'] === $height) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the size name defined in the configuration, where width
     * or height are equal to those given in parameters.
     *
     * Resized images keep the original image aspect ratio.
     * When an image is resized using the size from the configuration,
     * at least one of the borders has the same length.
     *
     * @param int $width
     * @param int $height
     *
     * @return bool `true` if the size from the configuration was used
     */
    protected function getSizeNameFromConfig($width, $height)
    {
        $configSizes = $this->config->get('images.sizes');

        foreach ($configSizes as $sizeName => $size) {
            if ($size['width'] === $width || $size['height'] === $height) {
                return $sizeName;
            }
        }

        return null;
    }

    protected function createNodeValue($resizedImage)
    {
        if (isset($resizedImage['url'])) {
            return [
                'name' => $resizedImage['basename'],
                'url' => $resizedImage['url'],
            ];
        }

        return $resizedImage['basename'];
    }
}
