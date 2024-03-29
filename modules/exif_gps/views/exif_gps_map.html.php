<?php defined("SYSPATH") or die("No direct script access.") ?>
<script type="text/javascript" src="http://www.google.com/jsapi"></script>
<script type="text/javascript">
<?
  if (module::get_var("exif_gps", "googlemap_api_key", "") != "") {
    print "google.load(\"maps\", \"3\",  {other_params:\"key=" . module::get_var("exif_gps", "googlemap_api_key") . "&sensor=false\"});";
  } else {
    print "google.load(\"maps\", \"3\",  {other_params:\"sensor=false\"});";
  }
?>

  var google_zoom_hack = false;
  var map = null;
  var infoWindow = null;

  $.ajaxSetup({
    error: function(xhr, status, error) {
      $('p.exif-gps-status').html("<font size=\"6\" color=\"white\"><strong>An AJAX error occured: " + 
                                  status + "<br />\nError: " + error + "</strong></font>");
    }
  });

  function initialize() {
    // Set up some initial variables and make a new map.
    var myLatlng = new google.maps.LatLng(0, 0);
    var myOptions = {
      zoom: 1,
      center: myLatlng,
      mapTypeId: google.maps.MapTypeId.<?=$map_type; ?>
    }
    map = new google.maps.Map(document.getElementById("map_canvas"), myOptions);

    //  This is so we can auto-center and zoom the map.
    //    We will grow this each time a new set of coordinates is added onto the map
    var glatlngbounds = new google.maps.LatLngBounds( );

    // Initialize the infoWindow.
    infoWindow = new google.maps.InfoWindow();

    // Set up some initial variables for looping through the coordinates in the xml files.
    var map_markers = []; // Array of all unique coordinates.
    var str_marker_html = ""; // infoWindow HTML for the current set of coordinates.
    var current_latlng = null; // Current coordinates.  Used for merging duplicate coordinates together.
    var item_counter = 0; // Current number of XML records that have been processed.
    var int_max_items = <?= $items_count; ?>; // Total number of XML records to process.
    var int_offset = 0; // Number of XML records to skip.  Sent as a URL parameter when retrieving XML data.

    //  Retrieve the first batch of XML records.
    //    This function runs recursively until item_counter equals int_max_items.
    //    We're using recursion instead of a normal looping control, because normal
    //    loops would prevent the web browser from responding until the loop is finished.
    get_xml();

    function get_xml() {
      // This function uses ajax requests to download and process a chunck of records, in XML format.
      jQuery.ajax({
        url:    '<?=url::abs_site("exif_gps/xml/{$query_type}/{$query_id}/"); ?>/' + int_offset,
        success: function(data) {
          jQuery(data).find("marker").each(function() {
            // Loop through the retrieved records and add each one to the map.

            // Process the current record.
            item_counter++;
            var xmlmarker = jQuery(this);
            var latlng = new google.maps.LatLng(parseFloat(xmlmarker.attr("lat")),
                                                parseFloat(xmlmarker.attr("lng")));

            // Group multiple records with the same lat and lng coordinates together into
            //   the same marker and info window.
            if (!latlng.equals(current_latlng)) {
              // If the latest record has different coordinates then the previous record
              //   and this is not the first record (!=null) then set up the click function
              //   for the previous marker...
              if (current_latlng != null) {
                var fn = markerClickFunction(str_marker_html, current_latlng);
                google.maps.event.addListener(map_markers[map_markers.length-1], 'click', fn);
              }

              // ... then set up a new marker for this record.
              current_latlng = latlng;
              str_marker_html = "";
              glatlngbounds.extend(latlng);
              var marker = new google.maps.Marker({position: latlng});
              map_markers.push(marker);
            }

            // Convert any XML escape characters in this record to regular text.
            var str_thumb_html = String(xmlmarker.attr("thumb"));
            str_thumb_html = str_thumb_html.replace("&lt;", "<");
            str_thumb_html = str_thumb_html.replace("&gt;", ">");
            str_thumb_html = str_thumb_html.replace("&apos;", "\'");
            str_thumb_html = str_thumb_html.replace("&quot;", "\"");
            str_thumb_html = str_thumb_html.replace("&amp;", "&");

            // Set up the HTML for this record and append it to the bottom of the info window.
            str_thumb_html = "<div class=\"g-exif-gps-thumb\"><a href=\"" + 
                              String(xmlmarker.attr("url")) + "\">" + str_thumb_html + "</a></div>";
            str_marker_html += str_thumb_html;
          });

          // Display a status message telling the user what percentage of records have been processed.
          $('p.exif-gps-status').html("<font size=\"6\" color=\"white\"><strong><?= t("Loading..."); ?> " + 
                                      parseInt((item_counter / int_max_items) * 100) + "%</strong></font>" + 
                                      "<br /><br /><img src=\"<?= url::file("modules/exif_gps/images/exif_gps-loading-map-large.gif"); ?>\"" + 
                                      " style=\"vertical-align: middle;\"></img>");

          // If item counter is less then max items, get the next batch of records.
          //  If item counter is equal to max items, then finish setting up the map and exit.
          if (item_counter < int_max_items) {
            int_offset += <?= EXIF_GPS_Controller::$xml_records_limit; ?>;
            get_xml();
          } else {
            // Set up the click function for the last coordinate.
            var fn = markerClickFunction(str_marker_html, current_latlng);
            google.maps.event.addListener(map_markers[map_markers.length-1], 'click', fn);

            // Add the coordinates to the map, grouping clusters of similar coordinates together.
            var mcOptions = { gridSize: <?= module::get_var("exif_gps", "markercluster_gridsize"); ?>, maxZoom: <?= module::get_var("exif_gps", "markercluster_maxzoom"); ?>};
            var markerCluster = new MarkerClusterer(map, map_markers, mcOptions);

            // Auto zoom and center the map around the coordinates.
            //  Set google_zoom_hack to true, to when the zoom changed function triggers 
            //  we can re-zoom to the admin specified auto-zoom value, if necessary.
            google_zoom_hack = true;
            map.fitBounds(glatlngbounds);

            // Hide the loading message and exit.
            document.getElementById('over_map').style.display = 'none';
          }
        }
      });
    }

    <? if (($max_autozoom = module::get_var("exif_gps", "googlemap_max_autozoom")) != "") : ?>
    // If there is a maximum auto-zoom value, then set up an event to check the zoom
    // level the first time it is changed, and adjust it if necessary.
    // (if we call map.getZoom right after .fitBounds, getZoom will return the initial 
    // zoom level, not the auto zoom level, this way we get the auto zoomed value).
    google.maps.event.addListener(map, 'zoom_changed', function() {
      if (google_zoom_hack) {
        if (map.getZoom() > <?= $max_autozoom ?>) map.setZoom(<?= $max_autozoom ?>);
        google_zoom_hack = false;
      }
    });
    <? endif ?>
  }

  // Set up an info window at the specified coordinates
  //   to display the specified html.
  markerClickFunction = function(str_thumb_html, latlng) {
    return function(e) {
      e.cancelBubble = true;
      e.returnValue = false;
      if (e.stopPropagation) {
        e.stopPropagation();
        e.preventDefault();
      }

      infoWindow.setContent(str_thumb_html);
      infoWindow.setPosition(latlng);
      infoWindow.open(map);
    };
  };

  google.setOnLoadCallback(initialize);
</script>

<style>
   #wrapper { position: relative; }
   #over_map { position: absolute; top: 0px; left: 0px; z-index: 99; }
</style>

<div id="g-exif-map-header">
  <div id="g-exif-map-header-buttons">
    <?= $theme->dynamic_top() ?>
  </div>
  <h1><?= html::clean($title) ?></h1>
</div>
<br />
<div id="wrapper">
  <div id="map_canvas" style="width:690px; height:480px;"></div>
  <div id="over_map" style="width:690px; height:480px;">
    <p id="exif-gps-status" class="exif-gps-status" style="text-align: center; display: table-cell; vertical-align: middle; width: 690px; height: 480px;">
      <font size="6" color="white"><strong><?= t("Loading..."); ?></strong></font><br /><br />
      <img src="<?= url::file("modules/exif_gps/images/exif_gps-loading-map-large.gif"); ?>" style="vertical-align: middle;"></img>
    </p>
  </div>
</div>
<?= $theme->dynamic_bottom() ?>
