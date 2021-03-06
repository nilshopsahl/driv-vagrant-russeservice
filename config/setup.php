<?php

/**
 * This file is run by the provision step of vagrant up
 */


chdir( '/vagrant' );
$dirs = glob( '*.local' );

// A temporary fix for the 403 by apache
// (Until the box is rebuilt with a propper apache config file)
if ( file_exists( 'config/tmp-fix-403.php' ) )
  require_once 'config/tmp-fix-403.php';

// Load the vhost template
$vhost_template = file_get_contents( 'config/vhost.conf' );

$sites = [];
foreach ( $dirs as $dir ) {
  // Skip non-dirs
  if ( !is_dir( $dir ) )
    continue;

  /**
   * APACHE2 - BEGIN
   */

  // Get the site name
  $site = basename( $dir );
  echo "Creating vhost for $site";

  // Create the site vhost file
  $site_vhost = str_replace( '%SITE', $site, $vhost_template );
  file_put_contents( "config/$site.conf", $site_vhost );

  // Remove vhost file if it exists
  if ( file_exists( "/etc/apache2/sites-available/$site.conf" ) )
    unlink( "/etc/apache2/sites-available/$site.conf" );

  // Enable the site
  `ln -s '/vagrant/config/$site.conf' /etc/apache2/sites-available`;
  `a2ensite '$site'`;

  /**
   * APACHE2 - END
   */


  /**
   * DATABASE - BEGIN
   */

  //$db_name = preg_replace( '/\W/', '_', $site );
  $db_names = array("russ","russ_v5");
  
  foreach ( $db_names as $db_name ) {
    `mysql -u root -e "CREATE DATABASE IF NOT EXISTS $db_name"`;
    if ( file_exists( "database/$db_name.sql" ) ) {
      echo "Importing $db_name.sql to database '$db_name'.....";
      `mysql -u root $db_name < "database/$db_name.sql"`;
      echo "Import to: '$db_name' finnished.";
    }
    echo "Set up database: '$db_name'";
  }
  

  /**
   * DATABASE - END
   */

  $sites[] = $site;
}

// Restart apache
`service apache2 restart`;

// Success
echo "Success, your sites will be available at the following urls:";
foreach ( $sites as $site ) {
  echo "http://$site:8080/";
}
echo "Make sure this line is in your hosts file:\n127.0.0.1\t". implode( ' ', $sites );
