<?php

   // Database connection settings
   define("PG_DB"  , "routing");
   define("PG_HOST", "127.0.0.1"); 
   define("PG_USER", "postgres");
   define("PG_PORT", "5432"); 
   define("TABLE",   "ways");

   // Retrieve start point
   $start = explode(' ',$_REQUEST['startpoint']);
   $startPoint = array($start[0], $start[1]);

   // Retrieve end point
   $end = explode(' ',$_REQUEST['finalpoint']);
   $endPoint = array($end[0], $end[1]);
   

   // Find the nearest edge
   $startEdge = findNearestEdge($startPoint);
   $endEdge   = findNearestEdge($endPoint);

   // FUNCTION findNearestEdge
   function findNearestEdge($lonlat) {

      // Connect to database
      $con = pg_connect("dbname=".PG_DB." host=".PG_HOST." user=".PG_USER);

      $sql = "SELECT gid, source, target, the_geom, 
		          ST_Distance(the_geom, ST_GeometryFromText(
	                   'POINT(".$lonlat[0]." ".$lonlat[1].")', 4326)) AS dist 
	             FROM ".TABLE."  
	             WHERE the_geom && ST_Setsrid(
	                   'BOX3D(".($lonlat[0]-0.1)." 
	                          ".($lonlat[1]-0.1).", 
	                          ".($lonlat[0]+0.1)." 
	                          ".($lonlat[1]+0.1).")'::box3d, 4326) 
	             ORDER BY dist LIMIT 1;";
				 
	  echo $sql;

      $query = pg_query($con,$sql);  

      $edge['gid']      = pg_fetch_result($query, 0, 0);  
      $edge['source']   = pg_fetch_result($query, 0, 1);  
      $edge['target']   = pg_fetch_result($query, 0, 2);  
      $edge['the_geom'] = pg_fetch_result($query, 0, 3);  

      // Close database connection
      pg_close($con);

      return $edge;
   }
   
   // Select the routing algorithm
   switch($_REQUEST['method']) {

      case 'SPD' : // Shortest Path Dijkstra 

        $sql = "SELECT route.id2, ST_AsGeoJSON(ways.the_geom) AS geojson, ST_length(ways.the_geom) AS length,ways.gid
				FROM pgr_dijkstra('SELECT gid AS id,
					source::integer,
					target::integer,
					length::double precision AS cost
					FROM ways', ".$startEdge['source'].", ".$endEdge['target'].", false, false ) 
				AS route LEFT JOIN ways ON route.id2 = ways.gid;";
        break;

   } // close switch

   // Connect to database
   print($sql);
   $dbcon = pg_connect("dbname=".PG_DB." host=".PG_HOST." user=".PG_USER);

   // Perform database query
   $query = pg_query($dbcon,$sql); 
   
   // Return route as GeoJSON
   $geojson = array(
      'type'      => 'FeatureCollection',
      'features'  => array()
   ); 
  
   // Add edges to GeoJSON array
   while($edge=pg_fetch_assoc($query)) {  

      $feature = array(
         'type' => 'Feature',
         'geometry' => json_decode($edge['geojson'], true),
         'crs' => array(
            'type' => 'EPSG',
            'properties' => array('code' => '4326')
         ),
         'properties' => array(
            'id' => $edge['id'],
            'length' => $edge['length']
         )
      );
      
      // Add feature array to feature collection array
      array_push($geojson['features'], $feature);
   }

	
   // Close database connection
   pg_close($dbcon);

   // Return routing result
   //header('Content-type: application/json',true);
   //echo json_encode($geojson);
?>
