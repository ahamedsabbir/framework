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

namespace CKSource\CKFinder\ResourceType;

use CKSource\CKFinder\CKFinder;
use Pimple\Container;

class ResourceTypeFactory extends Container
{
    protected $app;
    protected $config;
    protected $backendFactory;
    protected $thumbnailresources;

    public function __construct(CKFinder $app)
    {
        parent::__construct();

        $this->app = $app;
        $this->config = $app['config'];
        $this->backendFactory = $app['backend_factory'];
        $this->thumbnailresources = $app['thumbnail_resources'];
        $this->resizedImageresources = $app['resized_image_resources'];
    }

    /**
     * Returns the resource type object with a given name.
     *
     * @param string $name resource type name
     *
     * @return ResourceType
     */
    public function getResourceType($name)
    {
        if (!$this->offsetExists($name)) {
            $resourceTypeConfig = $this->config->getResourceTypeNode($name);
            $backend = $this->backendFactory->getBackend($resourceTypeConfig['backend']);

            $this[$name] = new ResourceType($name, $resourceTypeConfig, $backend, $this->thumbnailresources, $this->resizedImageresources);
        }

        return $this[$name];
    }
}
