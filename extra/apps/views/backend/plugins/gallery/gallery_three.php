<!DOCTYPE html>
<html lang="en" >
<head>
	<meta charset="UTF-8">
	<title>codepen - Bootstrap Image Gallery</title>
	<link rel='stylesheet' href='https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css'>
	<link rel='stylesheet' href='https://blueimp.github.io/Gallery/css/blueimp-gallery.min.css'>
	<?php echo backend_class::style("gstylethree", $plugins_type, $plugins); ?>
</head>
<body>
<!-- partial:index.partial.html -->
<div class="container">
  
  <div id="links">
    <!-- <a href="images/banana.jpg" title="Banana" data-gallery>
      <img src="images/thumbnails/banana.jpg" alt="Banana">
    </a>
    <a href="images/apple.jpg" title="Apple" data-gallery>
      <img src="images/thumbnails/apple.jpg" alt="Apple">
    </a>
    <a href="images/orange.jpg" title="Orange" data-gallery>
      <img src="images/thumbnails/orange.jpg" alt="Orange">
    </a>-->
  </div>
  
</div>  


<!-- The Bootstrap Image Gallery lightbox, should be a child element of the document body -->
<div id="blueimp-gallery" class="blueimp-gallery">
    <!-- The container for the modal slides -->
    <div class="slides"></div>
    <!-- Controls for the borderless lightbox -->
    <h3 class="title"></h3>
    <a class="prev">‹</a>
    <a class="next">›</a>
    <a class="close">×</a>
    <a class="play-pause"></a>
    <ol class="indicator"></ol>
    <!-- The modal dialog, which will be used to wrap the lightbox content -->
    <div class="modal fade">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" aria-hidden="true">&times;</button>
                    <h4 class="modal-title"></h4>
                </div>
                <div class="modal-body next"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default pull-left prev">
                        <i class="glyphicon glyphicon-chevron-left"></i>
                        Previous
                    </button>
                    <button type="button" class="btn btn-primary next">
                        Next
                        <i class="glyphicon glyphicon-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- partial -->
  <script src='https://cdnjs.cloudflare.com/ajax/libs/jquery/2.1.3/jquery.min.js'></script>
<script src='https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js'></script>
<script src='https://blueimp.github.io/Gallery/js/jquery.blueimp-gallery.min.js'></script>
<?php echo backend_class::script("gscriptthree", $plugins_type, $plugins); ?>
</body>
</html>
