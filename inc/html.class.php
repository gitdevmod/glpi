<?php
/*
 * @version $Id:
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2011 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 --------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

// class Html
class Html {



   /**
    * Clean display value deleting html tags
    *
    *@param $value string: string value
    *
    *@return clean value
   **/
   static function clean($value) {

      $value = preg_replace("/<(p|br)( [^>]*)?".">/i", "\n", $value);

      $specialfilter = array('@<span[^>]*?x-hidden[^>]*?>.*?</span[^>]*?>@si'); // Strip ToolTips
      $value = preg_replace($specialfilter, ' ', $value);

      $search = array('@<script[^>]*?>.*?</script[^>]*?>@si', // Strip out javascript
                      '@<style[^>]*?>.*?</style[^>]*?>@si',   // Strip style tags properly
                      '@<[\/\!]*?[^<>]*?>@si',                // Strip out HTML tags
                      '@<![\s\S]*?--[ \t\n\r]*>@');           // Strip multi-line comments including CDATA

      $value = preg_replace($search, ' ', $value);

      $value = preg_replace("/(&nbsp;| )+/", " ", $value);
      // nettoyer l'apostrophe curly qui pose probleme a certains rss-readers, lecteurs de mail...
      $value = str_replace("&#8217;", "'", $value);

   // Problem with this regex : may crash
   //   $value = preg_replace("/ +/u", " ", $value);
      $value = preg_replace("/\n{2,}/", "\n\n", $value,-1);

      return trim($value);
   }


   /**
    * Recursivly execute html_entity_decode on an Array
    *
    * @param $value string or array
    *
    * @return array of value (same struct as input)
   **/
   static function entity_decode_deep($value) {

      return (is_array($value) ? array_map(array(__CLASS__, 'entity_decode_deep'), $value)
                               : html_entity_decode($value, ENT_QUOTES, "UTF-8"));
   }


   /**
    * Recursivly execute htmlentities on an Array
    *
    * @param $value string or array
    *
    * @return array of value (same struct as input)
   **/
   static function entities_deep($value) {

      return (is_array($value) ? array_map(array(__CLASS__, 'entities_deep'), $value)
                               : htmlentities($value,ENT_QUOTES, "UTF-8"));
   }


   /**
    * Convert a date YY-MM-DD to DD-MM-YY for calendar
    *
    * @param $time date: date to convert
    *
    * @return $time or $date
   **/
   static function convDate($time) {

      if (is_null($time) || $time=='NULL') {
         return NULL;
      }

      if (!isset($_SESSION["glpidate_format"])) {
         $_SESSION["glpidate_format"] = 0;
      }

      switch ($_SESSION['glpidate_format']) {
         case 1 : // DD-MM-YYYY
            $date = substr($time, 8, 2)."-";  // day
            $date .= substr($time, 5, 2)."-"; // month
            $date .= substr($time, 0, 4);     // year
            return $date;

         case 2 : // MM-DD-YYYY
            $date = substr($time, 5, 2)."-";  // month
            $date .= substr($time, 8, 2)."-"; // day
            $date .= substr($time, 0, 4);     // year
            return $date;

         default : // YYYY-MM-DD
            if (strlen($time)>10) {
               return substr($time, 0, 10);
            }
            return $time;
      }
   }


   /**
    * Convert a date YY-MM-DD HH:MM to DD-MM-YY HH:MM for display in a html table
    *
    * @param $time datetime: datetime to convert
    *
    * @return $time or $date
   **/
   static function convDateTime($time) {

      if (is_null($time) || $time=='NULL') {
         return NULL;
      }

      return self::convDate($time).' '. substr($time, 11, 5);
   }


   /**
    * Clean string for input text field
    *
    * @param $string string: input text
    *
    * @return clean string
   **/
   static function cleanInputText($string) {
      return preg_replace('/\"/', '&quot;', $string);
   }


   /**
    * Clean all parameters of an URL. Get a clean URL
    *
    * @param $url string URL
    *
    * @return clean URL
   **/
   static function cleanParametersURL($url) {

      $url = preg_replace("/(\/[0-9a-zA-Z\.\-\_]+\.php).*/", "$1", $url);
      return preg_replace("/\?.*/", "", $url);
   }


   /**
    * Recursivly execute nl2br on an Array
    *
    * @param $value string or array
    *
    * @return array of value (same struct as input)
   **/
   static function nl2br_deep($value) {

      return (is_array($value) ? array_map(array(__CLASS__, 'nl2br_deep'), $value)
                               : nl2br($value));
   }


}
?>