<?php
/*

CDE Simple is for developers just beginning with PHP database development, it takes the differences between the PHP database implementations and
makes it easy to work and switch between the databases.  It also acts as the testing platform for the SQL translation tool.

*/
define( "CDE_OBJECT", 0 );
define( "CDE_ARRAY", 1 );
define( "CDE_ASSOC", 2 );
//removing magic quotes
if ( get_magic_quotes_gpc() ) {
  $process = array(
     &$_GET,
    &$_POST,
    &$_COOKIE,
    &$_REQUEST 
  );
  while ( list( $key, $val ) = each( $process ) ) {
    foreach ( $val as $k => $v ) {
      unset( $process[$key][$k] );
      if ( is_array( $v ) ) {
        $process[$key][stripslashes( $k )] = $v;
        $process[] =& $process[$key][stripslashes( $k )];
      } else {
        $process[$key][stripslashes( $k )] = stripslashes( $v );
      }
    }
  }
  unset( $process );
}
class CDESimple {
  var $dbh; //the database handle
  var $error; //any errors that may occur during an operation
  var $dbtype = "sqlite3"; //the default database type
  var $dbpath = ""; //path to the database format is ipaddress:databasepath / for sqlite we just use the path
  var $tmppath = "/tmp/"; //default path to temp space (only for sqlite)
  var $lastsql = Array( ); //the last sql that ran in an array
  var $lasterror = Array( ); //the last error before the current one
  var $debug = false; //set debug on or off
  var $affectedrows = 0; //affected rows or rows returned by a query
  var $nooffields = 0; //the no of columns or fields returned
  var $fieldinfo; //layout in an array of each field with its type and information
  var $version = "2.2"; //current version of CDE
  var $dbdateformat = "YYYY-mm-dd h:i:s"; //future functionality for date time conversion to the database for a database
  var $outputdateformat = "dd/mm/YYYY";
  var $updatefieldinfo = true; //this is turned off when doing computed field calculations, internal and expert use only
  var $RAWRESULT;
  var $lastrowid; //for sqlite autoincrement fields 
  
  /* Function to make a FPDF report - returns filename */
  function sql_report( $sql = "", $groupby = "", $outputpath = "output/", $companyname = "", $title = "", $extraheader = "somefunction", $orientation = "P", $pagesize = "A4", $totalcolumns = array( ), $compcolumns = array( ), $createcsv = false, $dontshow = "", //fields must be inputted separated by columns 
    $formats = "", $hidecolumns, $csvdelim = ",", $computedcolumns = array( ), $debug = false ) {
    $newoutputpath        = $_SERVER["DOCUMENT_ROOT"] . "/" . $outputpath;
    $pdf                  = new CDEFPDF( $orientation, "mm", $pagesize );
    $filename             = $pdf->execute( $newoutputpath, $title, $companyname, $this, $sql, $orientation, $pagesize, $groupby, $totalcolumns, $compcolumns, $extraheader, $createcsv, $dontshow, $formats, $hidecolumns, $csvdelim, $computedcolumns, $debug );
    $filename["filename"] = str_replace( $newoutputpath, $outputpath, $filename["filename"] );
    $filename["csvfile"]  = str_replace( $newoutputpath, $outputpath, $filename["csvfile"] );
    return $filename;
  }
  /*
  Function to create links tags.
  Especially useful if the links act as event triggers for javascript actions, as it defaults the href to: "javascript: void(0);"
  */
  function create_link( $caption = "Needs a caption", $onclick = "window.alert('CLICK!');", $class = "", $altevent = "", $style = "", $href = "javascript: void(0)" ) {
    if ( $style ) {
      $style = "style=\"{$style}\"";
    }
    if ( $class ) {
      $class = "class=\"{$class}\"";
    }
    if ( $onclick ) {
      $onclick = "onclick=\"{$onclick}\"";
    }
    $link = "<a href=\"{$href}\" {$onclick} {$class} {$style} {$altevent}>{$caption}</a>";
    return $link;
  }
  /* function to make it easy to get request variables out of the system */
  
  function get_inputs ($prefix="", $returnwithblanks=true, $requests="") {
    $result = array ();
    if ($requests == "") {
      $requests = $_REQUEST;
    }
    foreach ($requests as $rid => $request) {
      if (substr($rid, 0, strlen($prefix)) == $prefix) {
        if ($returnwithblanks) { 
          $result[substr($rid,strlen($prefix))] = $request;
        }
          else {
          if (trim ($request) != "") {
            $result[substr($rid,strlen($prefix))] = $request;
          }  
        }    
      }
    }

    return $result;
  }
  /*
  Function to clear floats,
  i.e. in an html5 website, tables aren't used for layout
  For a two column layout use the following example:
  ________________   _________________
  |                | |                 |
  |                | |                 |
  |    DIV1        | |    DIV2         |
  |  float: left   | |  float: left    |
  |                | |                 |
  |________________| |_________________|
  ->clear_float();
  
  If the float isn't cleared the content will not get naturally pushed down.
  */
  function clear_float( $side = "both" ) {
    $html .= "<br style=\"clear: {$side};\" />";
    return $html;
  }
  /*
  Function to encode raw image data
  caches the image into a folder for quick cleanup
  $imagedata = The blob or file contents
  $imagestore = the path to the file where it must be created.
  */
  function encode_image( $imagedata, $imagestore = "images", $size = "", $noimage = "/images/noimage.jpg" ) {
    if ( $size != "" )
      $thumbnail = true;
    if ( $imagedata == "" && $size != "" ) {
      $imagedata = file_get_contents( $_SERVER["DOCUMENT_ROOT"] . $noimage );
    }
    $imagehash = md5( $imagedata );
    if ( $thumbnail == true ) {
      $imagefile = $_SERVER["DOCUMENT_ROOT"] . "/{$imagestore}/thmb{$size}{$imagehash}";
      if ( !file_exists( $imagefile ) ) {
        file_put_contents( $imagefile, $imagedata );
        $makethumbnail = true;
      }
      $imagetype = exif_imagetype( $imagefile );
      //file_put_contents ($imagefile, $imagedata);
      //$makethumbnail = true;
      if ( $makethumbnail ) {
        //JPEG
        if ( $imagetype == 2 ) {
          $imagesrc = imagecreatefromjpeg( $imagefile );
        } else if ( $imagetype == 3 ) {
          $imagesrc = imagecreatefrompng( $imagefile );
        } else if ( $imagetype == 6 ) {
          $imagesrc = imagecreatefromwbmp( $imagefile );
        } else {
          //we don't know what file it is
          $makethumbnail = false;
        }
        if ( $makethumbnail ) {
          $thumbsize = explode( "x", $size );
          $thumbw    = $thumbsize[0];
          $thumbh    = $thumbsize[1];
          $imagesrcw = imagesx( $imagesrc );
          $imagesrch = imagesy( $imagesrc );
          if ( ( $thumbw == 0 ) && ( $thumbh == 0 ) ) {
            //image must be same size
            $thumbw = $imagesrcw;
            $thumbh = $imagesrch;
          } elseif ( $thumbh == 0 ) {
            $scalew = $thumbw / ( $imagesrcw - 1 );
            $thumbh = $imagesrch * $scalew;
          } elseif ( $thumbw == 0 ) {
            $scaleh = $thumbh / ( $imagesrch - 1 );
            $thumbw = $imagesrcw * $scaleh;
          }
          $thumbw     = (int) ( $thumbw );
          $thumbh     = (int) ( $thumbh );
          $imagethumb = imagecreatetruecolor( $thumbw, $thumbh );
          $white      = imagecolorallocate( $imagethumb, 255, 255, 255 );
          imagefill( $imagethumb, 0, 0, $white );
          //we need to work out aspect ratio otherwise we stretch
          if ( $thumbw > $thumbh ) { //width greater than height - the width will need to be adjusted on thumbnail
            $scaleh    = $thumbh / ( $imagesrch - 1 );
            $newthumbw = $imagesrcw * $scaleh;
            $newthumbh = $thumbh;
            $offsety   = 0;
            $offsetx   = ( $thumbw - $newthumbw ) / 2;
            $offsetx   = (int) ( $offsetx );
          } else { //height greater than width
            $scalew    = $thumbw / ( $imagesrcw - 1 );
            $newthumbh = $imagesrch * $scalew;
            $newthumbw = $thumbw;
            $offsetx   = 0;
            $offsety   = ( $thumbh - $newthumbh ) / 2;
            $offsety   = (int) ( $offsety );
          }
          $newthumbw = (int) ( $newthumbw );
          $newthumbh = (int) ( $newthumbh );
          //echo "copying {$newthumbw}X{$newthumbh} image";		
          if ( !imagecopyresized( $imagethumb, $imagesrc, $offsetx, $offsety, 0, 0, $newthumbw, $newthumbh, $imagesrcw, $imagesrch ) ) {
            imagedestroy( $imagethumb );
            imagedestroy( $imagesrc );
          } else {
            imagedestroy( $imagesrc );
            //create output thumbnail
            if ( imagejpeg( $imagethumb, $imagefile, 100 ) ) {
              imagedestroy( $imagethumb );
            }
          }
        }
      }
    } else {
      $imagefile = $_SERVER["DOCUMENT_ROOT"] . "/{$imagestore}/{$imagehash}";
      if ( !file_exists( $imagefile ) ) {
        file_put_contents( $imagefile, $imagedata );
      }
    }
    if ( $imagedata == "" ) {
      return "{$noimage}";
    } else {
      if ( $thumbnail ) {
        return "{$imagestore}/thmb{$size}{$imagehash}";
      } else {
        return "{$imagestore}/{$imagehash}";
      }
    }
  }
  /*
  Function dateinput to make cool dates
  
  style sheet snapshot below - style the root class to get the look and feel you want
  
    .currday a {
      color: red;  
    }
    
    .thismonth a {
      color: green;   
    }    
    
    .othermonth a {
      color: #DEDEDE;   
    }    
    .cal div {
       float:left;
       width:30px;
       height:20px;
       border: 1px solid blue; 
       text-align: center;
    }
    
    .cal .header {
       width:158px;
    }
  
  $CDE->dateinput ($name="edtDATE1", 80, "my calendar", $value="20/02/2004", $dateformat="dd/mm/YYYY", true, 15, "");

  
  */
  function dateinput( $name = "edt", $width = 100, $alttext = "", $value = "", $dateformat = "dd/mm/YYYY", $showtime = false, $minuteinterval = 15, $style = "dropdown" ) {
    //we need to have 3 selects, day, month , year
    if ( $value == "" ) {
      $value = $_REQUEST[$name];
    }
    if ( $value == "" ) {
      $value = date( str_replace( "dd", "d", str_replace( "mm", "m", str_replace( "YYYY", "Y", $dateformat ) ) ) );
    }
    $ddvalue   = substr( $value, strpos( $dateformat, "dd" ), 2 );
    $mmvalue   = substr( $value, strpos( $dateformat, "mm" ), 2 );
    $YYYYvalue = substr( $value, strpos( $dateformat, "YYYY" ), 4 );
    //$ddvalue . "-" . $mmvalue . "-" . $YYYYvalue;
    
    //explode the date
    
    $timepart = explode (" ", $value);
    if (count($timepart) == 2) {
      $timepart = $timepart[1];
      $timepart = explode(":", $timepart);
      $HHvalue = $timepart[0];
      $iivalue = $timepart[1];
    }    
    $ddlookup = "";
    for ( $i = 1; $i < 32; $i++ ) {
      $day = $i;
      if ( strlen( $day ) == 1 )
        $day = "0" . $day;
      $ddlookup .= "{$day},{$day}|";
    }
    $ddlookup = substr( $ddlookup, 0, -1 );
    $mmlookup = "";
    for ( $i = 1; $i < 13; $i++ ) {
      $month = $i;
      if ( strlen( $month ) == 1 )
        $month = "0" . $month;
      $mmlookup .= "{$month},{$month}|";
    }
    $mmlookup   = substr( $mmlookup, 0, -1 );
    $YYYYlookup = "";
    for ( $i = 2030; $i > 1900; $i-- ) {
      $YYYYlookup .= "{$i},{$i}|";
    }
    $YYYYlookup = substr( $YYYYlookup, 0, -1 );
    $event      = "onchange=\"lookupcal{$name}()\"";
    if ($style != "dropdown") {
      $this->deploy_javascript();
      $html .= "<input type=\"text\" name=\"{$name}\" id=\"{$name}\" value=\"{$value}\" style=\"width: {$width}px;\" title=\"{$alttext}\" >";
      $html .= "<div class=\"cdecal\" style=\"position: absolute;\" id=\"cal{$name}\"></div>";
      $html .= "<script type=\"text/javascript\" src=\"http://{$_SERVER["HTTP_HOST"]}/cdescript/cde.js\"></script>";            
      $html .= "<script>cde_calendar('cal{$name}', '{$name}', '{$value}', 0, '{$dateformat}', true);</script>";
    }
      else {
      $dd         = $this->select( "dd" . $name, $width = 50, $alttext = "Select day", $selecttype = "array", $ddlookup, $ddvalue, $event, $cssid = "", $readonly = false, $tabindex = "", true );
      $mm         = $this->select( "mm" . $name, $width = 50, $alttext = "Select month", $selecttype = "array", $mmlookup, $mmvalue, $event, $cssid = "", $readonly = false, $tabindex = "", true );
      $YYYY       = $this->select( "YYYY" . $name, $width = 60, $alttext = "Select year", $selecttype = "array", $YYYYlookup, $YYYYvalue, $event, $cssid = "", $readonly = false, $tabindex = "", true );
      $html       = str_replace( "dd", " ".$dd." ", $dateformat );
      $html       = str_replace( "mm", " ".$mm." ", $html );
      $html       = str_replace( "YYYY", " ".$YYYY, $html );
    }
    if ( $showtime ) {
      $HHlookup = "";
      for ( $i = 0; $i < 24; $i++ ) {
        $hour = $i;
        if ( strlen( $hour ) == 1 )
          $hour = "0" . $hour;
        $HHlookup .= "{$hour},{$hour}|";
      }
      $HHlookup = substr( $HHlookup, 0, -1 );
      $iilookup = "";
      for ( $i = 0; $i < 60; $i += $minuteinterval ) {
        $minute = $i;
        if ( strlen( $minute ) == 1 )
          $minute = "0" . $minute;
        $iilookup .= "{$minute},{$minute}|";
      }
      $iilookup = substr( $iilookup, 0, -1 );
      $HH       = $this->select( "HH" . $name, $width = 50, $alttext = "Select hour", $selecttype = "array", $HHlookup, $HHvalue, $event, $cssid = "", $readonly = false, $tabindex = "", true );
      $ii       = $this->select( "ii" . $name, $width = 50, $alttext = "Select minute", $selecttype = "array", $iilookup, $iivalue, $event, $cssid = "", $readonly = false, $tabindex = "", true );
      $html .= " Time: " . $HH . " H " . $ii;
    }
    $html .= "<input type=\"hidden\" name=\"{$name}\" value=\"\">";
    $html .= "<script> function lookupcal{$name}() {
                           var dateformat = '{$dateformat}';
                           var dd = document.forms[0].dd{$name}.value;  
                           var mm = document.forms[0].mm{$name}.value;
                           var YYYY = document.forms[0].YYYY{$name}.value;
                             
                           if (iscorrect = ((YYYY > 1900) && (YYYY < 2030))) {
                                if (iscorrect = (mm <= 12 && mm > 0)) {                
                                    var LY = (((YYYY % 4) == 0) && ((YYYY % 100) != 0) || ((YYYY % 400) == 0));   
                                    
                                    if(iscorrect = dd > 0) {
                                        if (mm == 2) {  
                                            iscorrect = LY ? dd <= 29 : dd <= 28;
                                            if (!iscorrect) dd = LY ? 29 : 28;
                                        } 
                                        else {
                                            if ((mm == 4) || (mm == 6) || (mm == 9) || (mm == 11)) {
                                                iscorrect = dd <= 30;
                                                if (!iscorrect) dd = 30;
                                            }
                                            else {
                                                iscorrect = dd <= 31;
                                                if (!iscorrect) dd = 31;
                                            }
                                        }
                                    }
                                      else {
                                      dd = new Date().getDay();  
                                    }
                                }
                                  else {
                                  mm = new Date().getMonth();  
                                }
                           } 
                            else {
                            YYYY = new Date().getFullYear();    
                          } 
                             
                          dateformat = dateformat.replace ('dd', dd);
                          dateformat = dateformat.replace ('mm', mm);
                          dateformat = dateformat.replace ('YYYY', YYYY);
                           
                          document.forms[0].dd{$name}.value = dd;  
                             
                          document.forms[0].{$name}.value = dateformat;                            
                       }
              </script>";
    return $html;
  }
  /*
  Function script to call javascript properly, with or without tags
  
  */
  function script( $scriptcode = "", $tags = true ) {
    if ( $tags ) {
      $html .= "<script>";
    }
    $html .= " try {
                eval('" . str_replace( "'", "\'", $scriptcode ) . "'); 
               }
                 catch(e) {
                 console.log ('CDE:'+e.message);
               }\n";
    if ( $tags ) {
      $html .= "</script>";
    }
    return $html;
  }
  /*
  Function to make an array out of the CDE object
  */
  function toArray( ) {
    return (array) $this;
  }
  /*
  Timer function to call javascript scripts repeatedly
  
  */
  function set_timer( $name = "tmrCDE", $function = "", $interval = 30000, $runonce = true, $callfunction = true, $returnscripttags = true ) {
    $html = "";
    if ( $function != "" ) {
      if ( $interval != 0 ) {
        if ( $returnscripttags ) {
          if ($runonce) {
            $runoncescript = " window.clearInterval({$name}); ";
          }
            else {
            $runoncescript = "";  
          }
          $html .= "<script> var {$name} = window.setInterval(function(){ {$runoncescript} try {  {$function}; } catch (err) { console.log('CDE:'+err.message); }  }, {$interval}); </script>\n";
          if ( $callfunction ) {
            $html .= "<script> {$function}; </script>\n";
          }
        } else {
          if ($runonce) {
            $runoncescript = " window.clearInterval({$name}); ";
          }
            else {
            $runoncescript = "";  
          }
          
          $html .= " var {$name} = window.setInterval(function(){  {$runoncescript} try { {$function}; } catch (err) { console.log ('CDE:'+err.message); } }, {$interval}); ";
          if ( $callfunction ) {
            $html .= "\n{$function};";
          }
        }
      } else {
        if ( $returnscripttags ) {
          $html .= "<script> window.clearInterval({$name}); </script>\n";
        } else {
          $html .= "window.clearInterval({$name});\n";
        }
      }
    }
    return $html;
  }
  /*function to make selects for html forms - must be html5 compliant 
  
  selecttype = array, sql, multiarray, multisql
  
  //must have a default of choose...
  
  $lookup = "0,Value1|1,Value2|2,Value3";
  
  */
  function select( $name = "edt", $width = 100, $alttext = "", $selecttype = "array", $lookup = "", $value = "", $event = "", $cssid = "", $readonly = false, $tabindex = "", $nochoose = false ) {
    if ( $_REQUEST[$name] ) {
      if ( $value == "" ) {
        $value = $_REQUEST[$name];
      }
    }
    if ( $selecttype == "array" || $selecttype == "multiarray" ) {
      $lookuplist = explode( "|", $lookup );
    } else if ( $selecttype == "sql" || $selecttype == "multisql" ) {
      $lookuprow = $this->get_row( $lookup, 1 ); //format [0] = NAME
      foreach ( $lookuprow as $irow => $row ) {
        $lookuplist[$irow] = $row[0] . "," . $row[1]; //make it in the form of array
      }
    }
    //make options for the type of select etc .....
    if ( $selecttype == "multiarray" || $selecttype == "multisql" ) {
      $options = "multiple=\"multiple\"";
    } else {
      $options = "";
    }
    if ( $readonly == true ) {
      $disabled = "disabled=\"disabled\"";
    } else {
      $disabled = "";
    }
    //default text
    if ($alttext == "") {
      $alttext = "Choose";
    }
    if ( $cssid != "" ) {
      $cssid = "id=\"{$cssid}\"";
    } else {
      $cssid = "";
    }
    $html = "<select $cssid style=\"width:{$width}px\" name=\"{$name}\" $options {$event} $disabled >";
    
    if (!$nochoose) {
      $html .= "<option value=\"\">{$alttext}</option>";
    }
    
    foreach ( $lookuplist as $lid => $option ) {
      $option = explode( ",", $option );
      if ( $value == $option[0] ) {
        $html .= "<option selected=\"selected\" value=\"{$option[0]}\">{$option[1]}</option>";
      } else {
        $html .= "<option value=\"{$option[0]}\">{$option[1]}</option>";
      }
    }
    $html .= "</select>";
    return $html;
  }
  /*function to take row variables to request variables*/
  function torequest( $row, $prefix = "" ) {
    foreach ( $row as $name => $value ) {
      $_REQUEST[$prefix . $name] = $value;
    }
  }
  /* function to make inputs for html forms - must be html5 compliant */
  function input( $name = "edt", $width = 100, $alttext = "", $compulsory = "", $inputtype = "text", $value = "", $event = "", $cssid = "", $readonly = false, $maxlength = "", $placeholder = "", $tabindex = "" ) {
    if ( $_REQUEST[$name] ) {
      if ( $value == "" ) {
        $value = $_REQUEST[$name];
      }
    }
    if ( $placeholder == "" ) {
      $placeholder = $alttext;
    }
    if ( $compulsory == "*" ) {
      $compulsory = "required=\"required\"";
    }
    $html  = "";
    $align = "";
    if ( $inputtype == "textarea" ) {
      $injection = "";
      if ( $width != "" ) {
       if (strpos($width, "%") === false && strpos($width, "em") === false) {
         $width = $width."px";
       }
       $injection .= " style=\"width:{$width}\"";
      }
      if ( $maxlength != "" ) {
        $injection .= " maxlength=\"{$maxlength}\"";
      }
      if ( $cssid != "" ) {
        $injection .= " id=\"{$cssid}\"";
      }
      if ( $event != "" ) {
        $injection .= " {$event}";
      }
      if ( $tabindex != "" ) {
        $injection .= " tabindex=\"{$tabindex}\"";
      }
      if ( $readonly ) {
        $injection .= " readonly=\"readonly\"";
      }
      $html .= "<textarea name=\"{$name}\" title=\"{$alttext}\" placeholder=\"{$placeholder}\" {$injection} {$compulsory} >{$value}</textarea>";
    } else {
      if ( $inputtype == "currency" ) {
        $inputtype = "text";
        $align     = "text-align:right;";
      }
      $html .= "<input";
      $html .= " type=\"{$inputtype}\" ";
      if ( $inputtype != "file" ) {
        $html .= " value=\"{$value}\" ";
      }
      if ( $alttext != "" ) {
        $html .= " title=\"{$alttext}\" ";
      }
      if ( $readonly ) {
        $html .= " readonly=\"readonly\"";
      }
      //echo "@".$_REQUEST[$name]." : $name ".$value."-";
      if ( ( $inputtype == "checkbox" || $inputtype == "radio" ) && trim( $_REQUEST[$name] ) == trim( $value ) ) {
        $html .= " checked=\"true\" ";
      }
      $html .= " name=\"{$name}\" ";
      if ( $width != 0 && $width != "" ) {
        $html .= " style=\"width:{$width}px; {$align}\" ";
      }
      if ( $event != "" ) {
        $html .= " $event ";
      }
      if ( $placeholder != "" && $inputtype != "button" ) {
        $html .= " placeholder=\"{$placeholder}\" ";
      }
      if ( $maxlength != "" ) {
        $html .= " maxlength=\"{$maxlength}\" ";
      }
      if ( $cssid != "" ) {
        if ( $inputtype == "button" ) {
          $html .= " class=\"$cssid\" ";
        } else {
          $html .= " id=\"$cssid\"";
        }
      }
      $html .= $compulsory;
      $html .= " />";
    }
    return $html;
  }
  /* This one has more explaining 
  see examples in documentation
  */
  /*
  function cleanurl is aimed at developers using the get method to pass form data.
  The function creates a javascript function that will go through the form and disable any controls that don't have values.
  In turn these controls will not submit any data to the next page, thus cleaning up the query string.
  */
  function cleanurl( $form = "forms[0]" ) {
    $html = "";
    $html .= "<script>function cleanurl() { f = document.{$form}; for (i = 0; i < f.elements.length; i++) { if (f.elements[i].value == '') { f.elements[i].disabled = true;} } }</script>";
    return $html;
  }
  function switchid( $name, $prescript = "", $form = "forms[0]" ) {
    $html = "";
    $html .= "<input type=\"hidden\" name=\"" . $name . "\" value=\"" . $_REQUEST[$name] . "\">\n";
    $html .= "<script language=\"Javascript\"> function set" . $name . " (i, canpost) { if (canpost === undefined) {canpost = false;}  $prescript document." . $form . "." . $name . ".value = i; if(canpost) { if (typeof cleanurl == 'function') { cleanurl(); } document." . $form . ".submit();}} </script>\n";
    if (!function_exists ("set".$name)) {
      eval( 'function set' . $name . '($id, $post=false) { $script = "document.' . $form . '.' . $name . '.value = \'$id\';"; if ($post) { return "<script> {$script} if (typeof cleanurl == \'function\') { cleanurl(); } document.' . $form . '.submit(); </script>"; } else { return "<script> {$script} </script>"; }  }' );
    }
    return $html;
  }
  /* Error handling for the class */
  function CDESimple_Error( $errno, $errstr, $errfile, $errline ) {
    $backtrace = debug_backtrace();
    $classfile = $errfile;
    $classline = $errline;
    foreach ( $backtrace as $key => $value ) {
      if ( key_exists( "file", $value ) ) {
        if ( basename( $_SERVER["SCRIPT_FILENAME"] ) == basename( $value["file"] ) ) {
          $errfile = $value["file"];
          $errline = $value["line"];
        }
      }
    }
    $errorstyle = "font-family: Tahoma; color: red;";
    $canadd     = true;
    switch ( $errno ) {
      case E_USER_ERROR:
        //This is a fatal error and must stop the script!
        $errorstyle = "font-family: Courier New; color: red;";
        $errormsg   = "<span style=\"$errorstyle\"><b>CDE Simple Error </b>$classfile [$classline] [$errno] $errstr in $errfile on line $errline <br />\n</span>";
        if ( count( $this->lastsql ) > 0 ) {
          if ( $this->lastsql[count( $this->lastsql ) - 1] ) {
            $errormsg .= "<pre><span style=\"$errorstyle\">" . $this->get_error() . ":\n" . $this->lastsql[count( $this->lastsql ) - 1] . "</span></pre>";
          }
        }
        echo $errormsg;
        exit( 1 );
        break;
      case E_USER_WARNING:
        //This error will allow application to continue but its not so good!
        $errorstyle = "font-family: Courier New; color: blue;";
        $errormsg   = "<span style=\"$errorstyle\"><b>CDE Simple Warning </b>$classfile [$classline] [$errno] $errstr in $errfile on line $errline <br />\n</span>";
        if ( count( $this->lastsql ) > 0 ) {
          if ( $this->lastsql[count( $this->lastsql ) - 1] ) {
            $errormsg .= "<pre><span style=\"$errorstyle\">" . $this->get_error() . ":\n" . $this->lastsql[count( $this->lastsql ) - 1] . " </span></pre>";
          }
        } else {
          $errormsg .= "<pre><span style=\"$errorstyle\">" . $this->get_error() . ":\n</span></pre>";
        }
        break;
      case E_USER_NOTICE:
        //This error is notifying the user of something that they should think about
        $errorstyle = "font-family: Courier New; color: green;";
        $errormsg   = "<span style=\"$errorstyle\"><b>CDE Simple Notice </b>$classfile [$classline] [$errno] $errstr in $errfile on line $errline <br />\n</span>";
        break;
      default:
        //Not sure what the error is but read the screen - normally undeclared variables
        $errorstyle = "font-family: Courier New; color: purple;";
        $errormsg   = "<span style=\"$errorstyle\"><b>CDE Simple Unknown </b>$classfile [$classline] [$errno] $errstr in $errfile on line $errline <br />\n</span>";
        break;
    }
    //Assign the last error to error variable
    $this->error = $errormsg;
    if ( $canadd )
      $this->lasterror[count( $this->lasterror )] = $errormsg;
    if ( $canadd )
      echo $this->debug ? $errormsg : null;
  }
  /* Output the last error */
  function last_error( ) {
    return $this->lasterror[count( $this->lasterror ) - 1];
  }
  /* Output the last error */
  function get_lasterror( ) {
    return $this->lasterror[count( $this->lasterror ) - 1];
  }
  /* Constructor for CDESimple */
  function CDESimple( $dbpath = "", $username = "", $password = "", $dbtype = "sqlite", $debug = false, $outputdateformat = "YYYY-mm-dd" ) //possible options are dd/mm/YYYY dd-mm-YYYY dd.mm.YYYY mm/dd/YYYY ... YYYY-mm-dd etc ...
    {
    $this->debug = $debug;
    //Define error handler
    error_reporting( E_USER_ERROR | E_ALL | E_USER_WARNING | E_USER_NOTICE );
    set_error_handler( array(
       $this,
      'CDESimple_Error' 
    ) );
    $this->connect( $dbpath, $username, $password, $dbtype, $outputdateformat );
    //how do we handle dates for different databases
    $this->outputdateformat = $outputdateformat; //how do we want the dates given back to us ???   
  }
  /***************************************************************************** 
  BEGIN Connect
  Connect to database and create handle in $dbh
  */
  function connect( $dbpath = "", $username = "", $password = "", $dbtype = "sqlite", $outputdateformat = "YYYY-mm-dd h:i:s" ) {
    if ( $dbpath == "" ) {
      trigger_error( "No dbpath specified in " . __METHOD__ . " for " . $this->dbtype, E_USER_ERROR );
    } else {
      $this->dbpath = $dbpath;
      $this->dbtype = $dbtype;
    }
    /* ODBC Connection */
    if ( $this->dbtype == "odbc" ) {
      //the dbpath variable will hold the full odbc connection
      //Example : "Driver={SQL Server Native Client 11.0};Server=.\SQLExpress;Database=DBNAME;"
      $this->dbdateformat = "YYYY-mm-dd h:i:s"; //Assuming this is the default, override if wrong
      $this->dbh          = @odbc_pconnect( $this->dbpath, $username, $password );
    } else /*MSSQL srv native components */ if ( $this->dbtype == "mssqlnative" ) {
      $this->dbdateformat = "YYYY-mm-dd h:i:s";
      $dbpath             = explode( ":", $dbpath );
      $serverName         = $dbpath[0];
      if ( $username != "" ) {
        $connectionInfo["UID"] = $username;
        $connectionInfo["PWD"] = $password;
      }
      $connectionInfo["Database"] = $dbpath[1];
      if ( function_exists( "sqlsrv_connect" ) ) {
        $this->dbh = @sqlsrv_connect( $serverName, $connectionInfo );
      } else {
        trigger_error( "Please download and install PHP module for " . $this->dbtype . " from Microsoft Download Center.", E_USER_ERROR );
      }
    } else /*CUBRID*/ if ( $this->dbtype == "CUBRID" ) {
      //date format setting
      $this->dbdateformat = "YYYY-mm-dd h:i:s";
      $dbpath             = explode( ":", $dbpath );
      if ( function_exists( "cubrid_connect" ) ) //Changed as per recommendation of Esen @ CUBRID
        {
        if ( $dbpath[2] == "" )
          $dbpath[2] = 33000;
        $this->dbh = @cubrid_connect( $dbpath[0], $dbpath[2], $dbpath[1], $username, $password ); //this should NOT be a persistent connection, unecessary for CUBRID
      } else {
        trigger_error( "Please download and install PHP module for " . $this->dbtype, E_USER_ERROR );
      }
    } else /*SQLite*/ if ( $this->dbtype == "sqlite" ) {
      //date format setting
      $this->dbdateformat = "YYYY-mm-dd h:i:s";
      if ( function_exists( "sqlite_popen" ) ) {
        putenv( "TMP=" . $this->tmppath );
        $this->dbh = @sqlite_popen( $this->dbpath );
      } else {
        trigger_error( "Please enable PHP module for " . $this->dbtype, E_USER_ERROR );
      }
    } else /*SQLite3*/ if ( $this->dbtype == "sqlite3" ) {
      $this->dbdateformat = "YYYY-mm-dd h:i:s";
      if ( class_exists( "SQLite3" ) ) {
        putenv( "TMP=" . $this->tmppath );
        $this->dbh = new SQLite3( $this->dbpath );
      } else {
        trigger_error( "Please enable PHP module for " . $this->dbtype, E_USER_ERROR );
      }
    } else /*Firebird*/ if ( $this->dbtype == "firebird" ) {
      $this->dbdateformat = "dd.mm.YYYY h:i:s";
      //$outputdateformat = dd/mm/YYYY
      $outputdateformat   = str_replace( "dd", "%d", $outputdateformat );
      $outputdateformat   = str_replace( "mm", "%m", $outputdateformat );
      $outputdateformat   = str_replace( "YYYY", "%Y", $outputdateformat );
      //maybe a limitation on timestamp but who would want the hours minutes and seconds to be otherwise
      ini_set( "ibase.dateformat", $outputdateformat );
      ini_set( "ibase.timestampformat", $outputdateformat . " %H:%M:%S" );
      if ( function_exists( "ibase_pconnect" ) ) {
        $this->dbh = @ibase_pconnect( $dbpath, $username, $password );
      } else {
        trigger_error( "Please enable PHP module for " . $this->dbtype, E_USER_ERROR );
      }
    } else /*MySQL*/ if ( $this->dbtype == "mysql" ) {
      $this->dbdateformat = "YYYY-mm-dd h:i:s";
      if ( function_exists( "mysqli_connect" ) ) {
        $dbpath    = explode( ":", $dbpath );
        $this->dbh = new mysqli( $dbpath[0], $username, $password, $dbpath[1] );
      } else if ( function_exists( "mysql_connect" ) ) {
        $dbpath    = explode( ":", $dbpath );
        $this->dbh = @mysql_connect( $dbpath[0], $username, $password );
        @mysql_select_db( $dbpath[1] );
      } else {
        trigger_error( "Please enable PHP module for " . $this->dbtype, E_USER_ERROR );
      }
    } else /* Oracle */ if ( $this->dbtype == "oracle" ) {
      $this->dbdateformat = "YYYY-mm-dd h:i:s";
      if ( function_exists( "oci_connect" ) ) {
        $this->dbh = @oci_connect( $username, $password, $dbpath );
      } else {
        trigger_error( "Please enable PHP module for " . $this->dbtype, E_USER_ERROR );
      }
    } else /* Postgres */ if ( $this->dbtype == "postgres" ) {
      $dbpath             = explode( ":", $dbpath );
      $sconnect           = "host={$dbpath[0]} dbname={$dbpath[1]} user=$username password=$password ";
      $this->dbdateformat = "YYYY-mm-dd h:i:s";
      if ( function_exists( "pg_connect" ) ) {
        $this->dbh = @pg_connect( $sconnect );
      } else {
        trigger_error( "Please enable PHP module for " . $this->dbtype, E_USER_ERROR );
      }
    } else /* Microsoft SQL Server */ if ( $this->dbtype == "mssql" ) {
      $dbpath             = explode( ":", $dbpath );
      $this->dbdateformat = "YYYY-mm-dd h:i:s";
      if ( function_exists( "mssql_connect" ) ) {
        //MSSQL needs changes in the php.ini file - we need to make the user aware of this.
        ini_set( "mssql.textlimit", "2147483647" ); //We need to do this to make blobs work and it doesn't work!!!!!!
        ini_set( "mssql.textsize", "2147483647" ); //We need to do this to make blobs work  
        ini_set( "odbc.defaultlrl", "12024K" ); // this is the max size for blobs        
        ini_set( "mssql.datetimeconvert", "0" );
        $this->dbh = @mssql_connect( $dbpath[0], $username, $password );
        @mssql_select_db( $dbpath[1] );
      } else {
        trigger_error( "Please enable PHP module for " . $this->dbtype, E_USER_ERROR );
      }
    } else {
      trigger_error( "Please implement " . __METHOD__ . " for " . $this->dbtype, E_USER_ERROR );
    }
    
    //get the last error
    $this->get_error();
    
    /* Debugging for Connect */
    if ( !$this->dbh ) {
      if ( is_array( $dbpath ) ) {
        $tmpdbpath = "<b>Host:</b>" . $dbpath[0] . " ";
        $tmpdbpath .= "<b>Database:</b>" . $dbpath[1] . " ";
        $tmpdbpath .= "<b>Port:</b>" . $dbpath[2] . " ";
        $dbpath = $tmpdbpath;
      }
      trigger_error( "Could not establish connection for " . $dbpath . " in " . __METHOD__ . " for " . $this->dbtype, E_USER_ERROR );
    }
  }
  /* 
  END  Connect
  *****************************************************************************/
  /***************************************************************************** 
  BEGIN Close
  */
  function close( ) {
    $result = false;
    if ( !$this->dbh ) {
      trigger_error( "No database handle, use connect first in " . __METHOD__ . " for " . $this->dbtype, E_USER_WARNING );
    } else /* ODBC Connection */ if ( $this->dbtype == "odbc" ) {
      $result = @odbc_close( $this->dbh );
    } else /*MSSQL srv native components */ if ( $this->dbtype == "mssqlnative" ) {
      $result = @sqlsrv_close( $this->dbh );
    } else /*CUBRID*/ if ( $this->dbtype == "CUBRID" ) {
      $result = @cubrid_disconnect( $this->dbh );
    } else /*SQLite*/ if ( $this->dbtype == "sqlite" ) {
      $result = @sqlite_close( $this->dbh );
      $result = true;
    } else /*SQLite3*/ if ( $this->dbtype == "sqlite3" ) {
      $this->dbh->close();
      $result = true;
    } else /*Firebird*/ if ( $this->dbtype == "firebird" ) {
      $result = @ibase_close( $this->dbh );
      $result = true;
    } else /* Oracle */ if ( $this->dbtype == "oracle" ) {
      $result = @oci_close( $this->dbh );
      $result = true;
    } else /*MySQL*/ if ( $this->dbtype == "mysql" ) {
      if ( function_exists( "mysqli_connect" ) ) {
        $result = $this->dbh->close();
        $result = true;
      } else {
        $result = @mysql_close( $this->dbh );
        $result = true;
      }
    } else /* Postgres */ if ( $this->dbtype == "postgres" ) {
      $result = @pg_close( $this->dbh );
    } else /* Microsoft SQL Server */ if ( $this->dbtype == "mssql" ) {
      $result = @mssql_close( $this->dbh );
    } else {
      trigger_error( "Please implement " . __METHOD__ . " for " . $this->dbtype, E_USER_ERROR );
    }
    /* Debugging for Close */
    if ( $result ) {
      $this->dbh = "";
    } else {
      trigger_error( "Cant close $this->dbpath in " . __METHOD__ . " for " . $this->dbtype, E_USER_NOTICE );
    }
    return $result;
  }
  /* 
  END  Close
  *****************************************************************************/
  /***************************************************************************** 
  BEGIN set_database
  * 
  * This is more for a MYSQL type database where you can choose a different database once a connection has been made
  */
  function set_database( $dbname ) {
    if ( $this->dbh ) {
      /* ODBC Connection */
      if ( $this->dbtype == "odbc" ) {
        trigger_error( "Please implement " . __METHOD__ . " for " . $this->dbtype );
      } else if ( $this->dbtype == "mysql" ) {
        if ( function_exists( "mysqli_connect" ) ) {
          $this->dbh->select_db( $dbname );
        } else {
          @mysql_select_db( $dbname, $this->dbh );
        }
        return true;
      } else {
        trigger_error( "Please implement " . __METHOD__ . " for " . $this->dbtype );
      }
    } else {
      return false;
    }
  }
  /* 
  END  set_database
  *****************************************************************************/
  /***************************************************************************** 
  BEGIN get_instance A System Function
  
  Finds all types of a word and returns all the positions and word type
  
  */
  function get_instance( $word, $sql ) {
    $icount = 0;
    foreach ( $sql as $id => $value ) {
      if ( trim (str_replace ( ",", "", $value)) == trim ($word) ) {
        $instance[$icount] = $id;
        $icount++;
      }
    }
    return $instance;
  }
  /* 
  END  get_instance
  *****************************************************************************/
  /***************************************************************************** 
  BEGIN parsesql - System Function
  */
  function parsesql( $sql = "", $fromdbtype = "generic", $todbtype = "generic" ) {
    //ignore initially the fromdbtype & todbtype
    //get rid of weird sql
    $sql = str_replace( "'null'", "null", $sql );
    //first section - change limits in mysql to firebird - needs to be enhanced for many sub selects
    //flatten sql
    //echo $sql;
    if ( $this->dbtype == "mssql" || $this->dbtype == "mssqlnative" ) {
      $sql = str_replace( "first", "top", $sql );
      $sql = str_replace( "||", "+", $sql );
      $sql = str_replace( "timestamp", "datetime", $sql );
    }
    if ( $this->dbtype == "sqlite3" ) {
      $sql = str_replace( "'now'", "DateTime('now')", $sql );
    }
    if ( $this->dbtype == "mysql" ) {
      $sql = str_replace( "'now'", "CURRENT_TIMESTAMP", $sql );
    }
    if ( ( stripos( $sql, "update" ) !== false || stripos( $sql, "insert" ) !== false ) && ( $this->dbtype == "mssql" || $this->dbtype == "mssqlnative" ) ) {
      $sql = str_replace( "'now'", "GETDATE()", $sql );
    } else if ( ( stripos( $sql, "update" ) !== false || stripos( $sql, "insert" ) !== false ) && $this->dbtype == "CUBRID" ) {
      $sql = str_replace( "'now'", "CURRENT_TIMESTAMP()", $sql );
    }
    if ( stripos( $sql, "update" ) === false && stripos( $sql, "insert" ) === false && stripos( $sql, "delete" ) === false ) {
      $sql       = str_replace( "\n", "", $sql );
      $sql       = str_replace( "\r", "", $sql );
      $sql       = str_replace( " ,", ",", $sql );
      $sql       = str_replace( ", ", ",", $sql );
      $sql       = str_replace( "," ,", ", $sql );
      $parsedsql = explode( " ", $sql );
      //get rid of spaces ?
      foreach ( $parsedsql as $pid => $pvalue ) {
        $parsedsql[$pid] = trim( $pvalue );
        if ( $parsedsql[$pid] == "" )
          unset( $parsedsql[$pid] );
      }
      //fix the index of the array
      $newarray = Array( );
      $icount   = 0;
      foreach ( $parsedsql as $pid => $pvalue ) {
        $newarray[$icount] = $pvalue;
        $icount++;
      }
      $parsedsql = $newarray;
      if ( $this->dbtype == "postgres" && stripos( $sql, "blob" ) !== false ) //postgres doesn't know about blobs it uses oids
        {
        foreach ( $parsedsql as $id => $value ) {
          $value          = str_ireplace( "longblob", "oid", $value );
          $value          = str_ireplace( "shortblob", "oid", $value );
          $parsedsql[$id] = str_ireplace( "blob", "oid", $value );
        }
      } else if ( ( $this->dbtype == "mysql" || $this->dbtype == "sqlite3" ) && stripos( $sql, "first " ) !== false ) {
        //select first 1 skip 10 must become 
               
        $firsts = $this->get_instance( "first", $parsedsql );
               
        if ( count( $firsts ) > 0 ) {
          $icount = 0;
          //kill all the firsts 
          foreach ( $firsts as $id => $index ) {
            $limits[$icount] = $parsedsql[$index + 1];
            unset( $parsedsql[$index + 1] );
            unset( $parsedsql[$index] );
            if ( trim(strtolower( $parsedsql[$index + 2]) ) == "skip" ) {
              
              $limits[$icount] = $parsedsql[$index + 3] . "," . $limits[$icount];
              unset( $parsedsql[$index + 2] );
              unset( $parsedsql[$index + 3] );
            }
            $limits[$icount] = "limit " . $limits[$icount];
            
            $icount++;
          }
          //Add the first limit to the end of the parsedsql;
         
          
          array_push( $parsedsql, $limits[0] );
         
        }
      } else if ( $this->dbtype == "firebird" && stripos( $sql, "limit " ) !== false ) //check for MySQL or ORacle limit
        {
        //find all the selects
        $selects = $this->get_instance( "select", $parsedsql );
        $limits  = $this->get_instance( "limit ", $parsedsql );
        if ( count( $limits ) > 0 ) {
          //remove all the limits & parse for skip & first
          $icount = 0;
          foreach ( $limits as $id => $index ) {
            $firstskip[$icount] = $parsedsql[$index + 1];
            if ( stripos( $firstskip[$icount], ")" ) !== false ) {
              $parsedsql[$index + 1] = ")";
              $firstskip[$icount]    = str_replace( ")", "", $firstskip[$icount] );
            } else {
              unset( $parsedsql[$index + 1] );
            }
            $firstskip[$icount] = explode( ",", $firstskip[$icount] );
            unset( $parsedsql[$index] );
            $icount++;
          }
          //do the first & last select 
          if ( count( $firstskip[$icount - 1] ) == 1 ) {
            $parsedsql[$selects[0]] = "select first " . $firstskip[$icount - 1][0];
          } else {
            $parsedsql[$selects[0]] = "select first " . $firstskip[$icount - 1][1] . " skip " . $firstskip[$icount - 1][0];
          }
          //and then the rest
          if ( $icount > 1 ) {
            for ( $i = 1; $i < $icount; $i++ ) {
              if ( count( $firstskip[$i] ) == 1 ) {
                $parsedsql[$selects[$i]] = "(select first " . $firstskip[$i][0];
              } else {
                $parsedsql[$selects[$i]] = "(select first " . $firstskip[$i][1] . " skip " . $firstskip[$i][0];
              }
            }
          }
        }
        //print_r ($parsedsql);
      } else if ( ( $this->dbtype == "mssql" || $this->dbtype == "mssqlnative" ) && ( stripos( $sql, "blob" ) !== false || stripos( $sql, "date" ) !== false || stripos( $sql, "now" ) !== false ) ) {
        //check for blobs, dates and now
        $parsedsql = $sql;
        $sqlwords  = array(
           '/ blob/',
          '/ date /',
          '/\'now\'/' 
        );
        $repwords  = array(
           ' image null',
          ' datetime',
          ' GETDATE() ' 
        );
        $parsedsql = preg_replace( $sqlwords, $repwords, $parsedsql );
      }
      
      $newsql = "";
      if ( is_array( $parsedsql ) ) {
        foreach ( $parsedsql as $id => $value ) {
          if ( trim( $value ) != "" ) {
            $newsql .= $value . " ";
          }
        }
        $parsedsql = $newsql;
       
      }
    } else {
      $parsedsql = $sql;
    }
    
    
    $this->lastsql[count( $this->lastsql )] = $parsedsql; // save the last sql  
    return $parsedsql;
  }
  /* 
  END  parsesql
  *****************************************************************************/
  /***************************************************************************** 
  BEGIN escape_string - a generic escape string function
  */
  function escape_string( $data ) {
    if ( !isset( $data ) or empty( $data ) )
      return '';
    if ( is_numeric( $data ) )
      return $data;
    $non_displayables = array(
       '/%0[0-8bcef]/', // url encoded 00-08, 11, 12, 14, 15
      '/%1[0-9a-f]/', // url encoded 16-31
      '/[\x00-\x08]/', // 00-08
      '/\x0b/', // 11
      '/\x0c/', // 12
      '/[\x0e-\x1f]/' // 14-31
    );
    foreach ( $non_displayables as $regex )
      $data = preg_replace( $regex, '', $data );
    $data = str_replace( "'", "''", $data );
    return $data;
  }
  /* 
  END  escape_string
  *****************************************************************************/
  /***************************************************************************** 
  BEGIN Set Params - make all ? replaced with passed params
  */
  function set_params( $sql = "", $inputvalues = Array( ) ) {
    $lastplace = 1; //Mustn't go back in the replace
    $count     = 0;
    for ( $i = 1; $i < sizeof( $inputvalues ); $i++ ) {
      $tryme = $inputvalues[$i];
      if ( $this->dbtype != "CUBRID" )
        $inputvalues[$i] = str_replace( "'", "''", $inputvalues[$i] ); //some strings have single ' which make it break on replacing!
      if ( $this->dbtype == "mysql" ) {
        if ( function_exists( "mysqli_connect" ) ) {
          $inputvalues[$i] = $this->dbh->real_escape_string( $inputvalues[$i] );
        } else {
          $inputvalues[$i] = mysql_real_escape_string( $inputvalues[$i] );
        }
      }
      if ( $this->dbtype == "sqlite" ) {
        $inputvalues[$i] = sqlite_escape_string( $inputvalues[$i] );
      }
        else   
      if ( $this->dbtype == "sqlite3" ) {
        $inputvalues[$i] = $this->escape_string( $inputvalues[$i] );
      } else   
      if ( $this->dbtype == "CUBRID" ) {
        $inputvalues[$i] = $this->escape_string( $inputvalues[$i] );
      }  
      $inputvalues[$i] = "'" . $inputvalues[$i] . "'";
      $lastpos         = 1;
      while ( $lastpos <> 0 ) {
        $lastpos = strpos( $sql, "?", $lastplace );
        if ( $lastpos == "" )
          break; //This checks that lastpos
        if ( $sql[$lastpos - 1] != "<" || $sql[$lastpos + 1] != ">" ) {
          $sql       = substr_replace( $sql, $inputvalues[$i], $lastpos, 1 );
          $lastplace = $lastpos + strlen( $inputvalues[$i] );
        }
        $lastpos = 0;
      }
      $count++;
    }
    return $sql;
  }
  /* 
  END  Set Params
  *****************************************************************************/
  /***************************************************************************** 
  BEGIN Exec
  */
  function exec( $sql = "" ) {
    $inputvalues                            = func_get_args();
    $this->error                            = ""; // reset the last error;   
    $result                                 = false;
    $this->lastsql[count( $this->lastsql )] = "preparse: " . $sql;
    $sql                                    = $this->parsesql( $sql );
    $sql                                    = explode( ";\n", $sql ); //see if more than one statment - ; separated with line feed
    if ( !$this->dbh ) {
      trigger_error( "No database handle, use connect first in " . __METHOD__ . " for " . $this->dbtype, E_USER_WARNING );
    } else /* ODBC Connection */ if ( $this->dbtype == "odbc" ) {
      foreach ( $sql as $id => $script ) {
        $query     = @odbc_prepare( $this->dbh, $script );
        $params    = array( );
        $params[0] = $query;
        //what if we have passed some parameters - firebird can do this
        for ( $i = 1; $i < func_num_args(); $i++ ) {
          $params[$i] = func_get_arg( $i );
        }
        if ( sizeof( $params ) != 0 ) {
          $result = @call_user_func_array( "odbc_execute", $params );
        } else {
          $result = @odbc_execute( $query );
        }
      }
    } else /*Microsoft SQL Server*/ if ( $this->dbtype == "mssqlnative" ) {
      foreach ( $sql as $id => $script ) {
        $script = $this->set_params( $script, $inputvalues );
        $result = @sqlsrv_query( $this->dbh, $script );
        if ( $result != false ) {
          $result = "No Errors";
        } else {
          $result = $this->get_error();
        }
        //help with the debugging on mssql
        if ( $result != "No Errors" ) {
          $result = false;
        } else {
          $result = true;
        }
      }
    } else /*CUBRID*/ if ( $this->dbtype == "CUBRID" ) {
      foreach ( $sql as $id => $script ) {
        $script = $this->set_params( $script, $inputvalues );
        $result = @cubrid_execute( $this->dbh, $script );
        if ( $result ) {  
          $result = true;
          $this->lastrowid = @cubrid_insert_id();
        }
      }
    } else /*SQLite*/ if ( $this->dbtype == "sqlite" ) {
      foreach ( $sql as $id => $script ) {
        $script = $this->set_params( $script, $inputvalues );
        
        
        @sqlite_exec( $this->dbh, $script, $result );
        if ( $result == "" ) {
          $result .= "No Errors\n";
        }
      }
    } else /*SQLite3*/ if ( $this->dbtype == "sqlite3" ) {
      foreach ( $sql as $id => $script ) {
        //$script = $this->set_params( $script, $inputvalues );
        if (strpos($script, "?") !== false) {
          
          $query = $this->dbh->prepare ($script);
          
          $params    = array( );        
          for ( $i = 1; $i < func_num_args(); $i++ ) {
            $params[$i] = func_get_arg( $i );
          }
          foreach ($params as $pid => $param) {
            if ( preg_match(':^(\P{Cc}|[\t\n])*$:', $param)) {
              if (is_float($param)) {
                $query->bindValue ($pid, $param, SQLITE3_FLOAT);
              }
              else
              if (is_int($param)) {
                $query->bindValue ($pid, $param, SQLITE3_INTEGER);
              }
              else 
              if (is_string($param)) {
               
                $query->bindValue ($pid, $param);
                
              }
            }
             else {
              $query->bindValue ($pid, $param, SQLITE3_BLOB);  
            }
          }
          $result = $query->execute();
        }
          else {
          $result = $this->dbh->exec ($script);  
        }
        
        if ( $result == 1 ) {
          $this->lastrowid = $this->dbh->lastInsertRowID();
        }
      }
    } else /*Firebird*/ if ( $this->dbtype == "firebird" ) {
      foreach ( $sql as $id => $script ) {
        $query     = @ibase_prepare( $this->dbh, $script );
        $params    = array( );
        $params[0] = $query;
        //what if we have passed some parameters - firebird can do this
        for ( $i = 1; $i < func_num_args(); $i++ ) {
          $params[$i] = func_get_arg( $i );
        }
        if ( sizeof( $params ) != 0 ) {
          $result = @call_user_func_array( "ibase_execute", $params );
        } else {
          $result = @ibase_execute( $query );
        }
      }
    } else /*Oracle*/ if ( $this->dbtype == "oracle" ) {
      foreach ( $sql as $id => $script ) {
        $script = $this->set_params( $script, $inputvalues );
        $query  = @oci_parse( $this->dbh, $script );
        $result = @oci_execute( $query );
      }
    } else /*MySQL*/ if ( $this->dbtype == "mysql" ) {
      foreach ( $sql as $id => $script ) {
        if ( function_exists( "mysqli_connect" ) ) {
          $script                                 = $this->set_params( $script, $inputvalues );
          $this->lastsql[count( $this->lastsql )] = "preparse: " . $script;
          if ( $result = $this->dbh->query( $script ) ) {
            //$this->dbh->free_result($result);
            $result = "No Errors";
          } else {
            //add the last error message
            $this->lasterror[count( $this->lasterror )] = $this->dbh->error;
          }
        } else {
          $script = $this->set_params( $script, $inputvalues );
          $result = @mysql_query( $script, $this->dbh );
          if ( $result != false ) {
            $result = "No Errors";
          } else {
            $result = $this->get_error();
          }
        }
      }
    } else /*Postgres*/ if ( $this->dbtype == "postgres" ) {
      foreach ( $sql as $id => $script ) {
        $script = $this->set_params( $script, $inputvalues );
        $result = @pg_query( $script );
        if ( $result != false ) {
          $result = "No Errors";
        } else {
          $result = $this->get_error();
        }
      }
    } else /*Microsoft SQL Server*/ if ( $this->dbtype == "mssql" ) {
      foreach ( $sql as $id => $script ) {
        $script = $this->set_params( $script, $inputvalues );
        $result = @mssql_query( $script );
        if ( $result != false ) {
          $result = "No Errors";
        } else {
          $result = $this->get_error();
        }
        //help with the debugging on mssql
        if ( $result != "No Errors" ) {
          print_r( $result );
        }
      }
      //echo $script;
    } else {
      trigger_error( "Please implement " . __METHOD__ . " for " . $this->dbtype, E_USER_ERROR );
    }
    
    //get the last error
    $this->get_error();
    
    /* Debugging for Exec */
    if ( $result ) {
      return $result;
    } else {
      trigger_error( "Cant run " . __METHOD__ . " for " . $this->dbtype, E_USER_WARNING );
    }
  }
  /* 
  END  Exec
  *****************************************************************************/
  /***************************************************************************** 
  BEGIN Commit
  */
  function commit( ) {
    if ( !$this->dbh ) {
      trigger_error( "No database handle, use connect first in " . __METHOD__ . " for " . $this->dbtype, E_USER_WARNING );
    } else /*ODBC Connection */ if ( $this->dbtype == "odbc" ) {
      $result = @odbc_commit( $this->dbh );
    } else /*MS SQL Native */ if ( $this->dbtype == "mssqlnative" ) {
      $result = @sqlsrv_commit( $this->dbh );
    } else /*CUBRID*/ if ( $this->dbtype == "CUBRID" ) {
      $result = @cubrid_commit( $this->dbh );
    } else /*SQLite*/ if ( $this->dbtype == "sqlite" || $this->dbtype == "sqlite3" ) {
      trigger_error( "Unsupported feature in " . __METHOD__ . " for " . $this->dbtype, E_USER_NOTICE );
      $result = true;
    } else /*Firebird*/ if ( $this->dbtype == "firebird" ) {
      $result = @ibase_commit();
    } else /*Oracle*/ if ( $this->dbtype == "oracle" ) {
      $result = @oci_commit( $this->dbh );
    } else /*MySQL*/ if ( $this->dbtype == "mysql" ) {
      if ( function_exists( "mysqli_connect" ) ) {
        $this->dbh->commit();
      } else {
        trigger_error( "Unsupported feature in " . __METHOD__ . " for " . $this->dbtype, E_USER_NOTICE );
        $result = true;
      }
    } else /*Postgres*/ if ( $this->dbtype == "postgres" ) {
      //Please test this !!!
      @pg_query( $this->dbh, "commit" );
      //0trigger_error ("Please implement ".__METHOD__." for ".$this->dbtype, E_USER_ERROR);
      $result = true;
    } else /*Microsoft SQL Server*/ if ( $this->dbtype == "mssql" ) {
      trigger_error( "Unsupported feature in " . __METHOD__ . " for " . $this->dbtype, E_USER_NOTICE );
      $result = true;
    } else {
      trigger_error( "Please implement " . __METHOD__ . " for " . $this->dbtype, E_USER_ERROR );
    }
    
    //see if there was an error
    $this->get_error();
    
    /* Debugging for Commit */
    if ( $result ) {
      return $result;
    } else {
      trigger_error( "Cant run " . __METHOD__ . " for " . $this->dbtype, E_USER_WARNING );
    }
  }
  /* 
  END  Commit
  *****************************************************************************/
  /***************************************************************************** 
  BEGIN Get Error - last database error
  */
  function get_error( ) {
    $result = false;
    /* ODBC Connection */
    if ( $this->dbtype == "odbc" ) {
      $result = @odbc_error( $this->dbh ) . " " . @odbc_errormsg( $this->dbh );
    } else /*MSSQL srv native components */ if ( $this->dbtype == "mssqlnative" ) {
      $result = @sqlsrv_errors();
    } else /*CUBRID*/ if ( $this->dbtype == "CUBRID" ) {
      $result = @cubrid_error_code() . " " . @cubrid_error_msg();
    } else /*SQLite*/ if ( $this->dbtype == "sqlite" ) {
      $result = @sqlite_error_string( sqlite_last_error( $this->dbh ) );
    } else /*SQLite3*/ if ( $this->dbtype == "sqlite3" ) {
      $result = $this->dbh->lastErrorMsg();
      if ( $result == "not an error" )
        $result = "";
    } else /*Firebird*/ if ( $this->dbtype == "firebird" ) {
      $result = @ibase_errmsg();
    } else /*Oracle*/ if ( $this->dbtype == "oracle" ) {
      $result = @oci_error();
    } else /*MySQL*/ if ( $this->dbtype == "mysql" ) {
      if ( function_exists( "mysqli_connect" ) ) {
        $result = $this->dbh->error;
      } else {
        $result = @mysql_error( $this->dbh );
      }
    } else /*Postgres*/ if ( $this->dbtype == "postgres" ) {
      $result = @pg_last_error( $this->dbh );
    } else /*Microsoft SQL Server*/ if ( $this->dbtype == "mssql" ) {
      $result = @mssql_get_last_message();
    } else {
      trigger_error( "Please implement " . __METHOD__ . " for " . $this->dbtype, E_USER_ERROR );
    }
    
    if ($result == "") $result = "No Error";
    $this->lasterror[count( $this->lasterror )] = $result;
    
    return $result;
  }
  /* 
  END  Get Error
  *****************************************************************************/
  
  
  function get_data_type ($data) {
    if (!function_exists ("is_datetime")) {
      function is_datetime($data) {
        if (date('Y-m-d H:i:s', strtotime($data)) == $data) {
            return true;
        } else {
            return false;
        }
      }
    }
    
    if (strpos($date, "-") !== false || strpos ($date, "/") !== false) {
      if (is_datetime($data)) {
        $type = "DATETIME";
      }
      else
       if (is_numeric ($type)) {
         $type = "NUMERIC";     
      }   
        else {
         $type = "VARCHAR";  
      }
    }
      else
    if (is_numeric ($type)) {
      $type = "NUMERIC";     
    }   
      else {
      $type = "VARCHAR";  
    }
    
    return $type;
  }
  
  /***************************************************************************** 
  BEGIN Get Row - Fetch a row in a number of formats
  $rowtype = 0 - Object
  1 - Array
  2 - Array Indexed by Field Name 
  
  if $fetchblob is set to false then the blob id's are returned 
  
  17/04/2012 - Added calculated fields
  
  
  $calculatedfields["field alias"] = 'php function name';
  
  updatefieldinfo should be turned off when doing sub selects to get information
  
  */
  function get_row( $sql = "", $rowtype = 0, $fetchblob = true, $calculatedfields = array( ) ) {
    //parse the calculated fields to normalize array
    $newarr = array( );
    foreach ( $calculatedfields as $cid => $calcfield ) {
      $newarr[strtoupper( $cid )] = $calcfield;
    }
    $calculatedfields = $newarr;
    $result           = false;
    //Clear the field data for new query
    if ( $this->updatefieldinfo ) {
      unset( $this->fieldinfo );
    } else { //store the field info
      $tempfieldinfo = $this->fieldinfo;
    }
    //Dont matter if there is no sql - use the last one.
    if ( $sql == "" )
      $sql = $this->lastsql[count( $this->lastsql ) - 1];
    $sql = $this->parsesql( $sql );
    /* ODBC Connection */
    if ( $this->dbtype == "odbc" ) {
      //get odbc results
      $query  = @odbc_exec( $this->dbh, $sql );
      $icount = 0;
      switch ( $rowtype ) {
        case 0: //Object
          while ( $row = @odbc_fetch_object( $query ) ) {
            $result[$icount] = $row;
            $icount++;
          }
          break;
        case 1: //Index
          $icount = @odbc_num_rows( $query );
          for ( $irow = 0; $irow < $icount; $irow++ ) {
            @odbc_fetch_into( $query, $row, $irow );
            $result[$irow] = $row;
          }
          break;
        case 2: //Associative Index
          while ( $row = @odbc_fetch_array( $query ) ) {
            $result[$icount] = $row;
            $icount++;
          }
          break;
      }
      $this->nooffields = @odbc_num_fields( $query );
      //odbc field numbering starts at 1 we need it at 0 base
      for ( $i = 1; $i <= $this->nooffields; $i++ ) {
        $column_name                       = @odbc_field_name( $query, $i );
        $column_type                       = @odbc_field_type( $query, $i );
        $column_size                       = @odbc_field_len( $query, $i );
        $this->fieldinfo[$i - 1]["name"]   = strtoupper( $column_name );
        $this->fieldinfo[$i - 1]["alias"]  = strtoupper( $column_name );
        $this->fieldinfo[$i - 1]["length"] = $column_size;
        $this->fieldinfo[$i - 1]["type"]   = strtoupper( $column_type );
        $this->fieldinfo[$i - 1][1]        = strtoupper( $column_name );
        ;
        $this->fieldinfo[$i - 1][0] = strtoupper( $column_name );
        ;
        $this->fieldinfo[$i - 1][2] = $column_size;
        $this->fieldinfo[$i - 1][4] = strtoupper( $column_type );
      }
      $this->affectedrows = $icount;
    } else /*Microsoft SQL Server Native Drivers*/ if ( $this->dbtype == "mssqlnative" ) {
      //build an array of results
      $query  = @sqlsrv_query( $this->dbh, $sql );
      //echo $sql;     
      $icount = 0;
      switch ( $rowtype ) {
        case 0: //Object
          while ( $row = @sqlsrv_fetch_object( $query ) ) {
            $result[$icount] = $row;
            $icount++;
          }
          break;
        case 1: //Index
          while ( $row = @sqlsrv_fetch_array( $query, SQLSRV_FETCH_NUMERIC ) ) {
            $result[$icount] = $row;
            $icount++;
          }
          break;
        case 2: //Associative Index
          while ( $row = @sqlsrv_fetch_array( $query, SQLSRV_FETCH_ASSOC ) ) {
            $result[$icount] = $row;
            $icount++;
          }
          break;
      }
      $this->nooffields = @sqlsrv_num_fields( $query );
      $field_data       = sqlsrv_field_metadata( $query );
      // print_r ($field_data);     
      foreach ( $field_data as $i => $field ) {
        //code to determine aliases - testing in mssql - needs to be applied to all databases
        //by Rudy Smith   changed by Andre, thanks for the idea Rudy
        $origcol   = "";
        $origalias = explode( ",", $sql );
        foreach ( $origalias as $oid => $ovalue ) {
          if ( strpos( $ovalue, $field["Name"] ) !== false ) {
            $fieldname = explode( "as", $ovalue );
            $origcol   = trim( $fieldname[0] );
          }
        }
        if ( $origcol == "" ) {
          $origcol = strtoupper( $field["Name"] );
        }
        $column_type = "";
        switch ( $field["Type"] ) {
          case -4:
            $column_type = "BLOB";
            break;
          case 5:
          case 4:
            $column_type = "INTEGER";
            break;
          case 12:
          case -9:
            $column_type = "VARCHAR";
            break;
          case 2:
          case 3:
            $column_type = "NUMERIC";
            break;
          case 6:
            $column_type = "NUMERIC";
            break;
          case 91:
            $column_type = "DATE";
            break;
          case -2:
          case 93:
            $column_type = "DATETIME";
            break;
          case 91:
            $column_type = "DATETIME";
            break;
          default:
            $column_type = "Fix please: " . $field["Type"];
            break;
        }
        $this->fieldinfo[$i]["name"]   = strtoupper( $origcol );
        //echo "<br>";                                    
        $this->fieldinfo[$i]["alias"]  = $field["Name"];
        $this->fieldinfo[$i]["length"] = $field["Precision"];
        $this->fieldinfo[$i]["type"]   = strtoupper( $column_type );
        $this->fieldinfo[$i][1]        = strtoupper( $column_name );
        ;
        $this->fieldinfo[$i][0] = strtoupper( $column_name );
        ;
        $this->fieldinfo[$i][2] = $column_size;
        $this->fieldinfo[$i][4] = strtoupper( $column_type );
      }
      $this->affectedrows = $icount;
    } else /*CUBRID*/ if ( $this->dbtype == "CUBRID" ) {
      //still need to check the different modes
      $query  = @cubrid_execute( $this->dbh, $sql );
      $icount = 0;
      switch ( $rowtype ) {
        case 0: //Object
          while ( $row = @cubrid_fetch( $query, CUBRID_OBJECT ) ) {
            $result[$icount] = $row;
            $icount++;
          }
          break;
        case 1: //Index
          while ( $row = @cubrid_fetch( $query, CUBRID_NUM ) ) {
            $result[$icount] = $row;
            $icount++;
          }
          break;
        case 2: //Associative Index
          while ( $row = @cubrid_fetch( $query, CUBRID_ASSOC ) ) {
            $result[$icount] = $row;
            $icount++;
          }
          break;
      }
      //get the columns
      $this->nooffields = @cubrid_num_cols( $query );
      $column_names     = cubrid_column_names( $query );
      $column_types     = cubrid_column_types( $query );
      //iterate the columns
      for ( $i = 0; $i < $this->nooffields; $i++ ) {
        $this->fieldinfo[$i]["name"] = $column_names[$i];
        if ( $column_types[$i] == "timestamp" )
          $column_types[$i] = "DATETIME";
        $this->fieldinfo[$i]["type"]  = strtoupper( $column_types[$i] );
        $this->fieldinfo[$i]["alias"] = ucwords( strtolower( $this->fieldinfo[$i]["name"] ) );
        $this->fieldinfo[$i][1]       = $this->fieldinfo[$i]["name"];
        $this->fieldinfo[$i][0]       = $this->fieldinfo[$i]["alias"];
        //$this->fieldinfo[$i][2] = $column_size; //need to calculate this
        $this->fieldinfo[$i][4]       = $this->fieldinfo[$i]["type"];
      }
      $this->affectedrows = $icount;
      //free up the results, CDE now has them in memory anyway
      @cubrid_free_result( $this->dbh );
    } else /*SQLite*/ if ( $this->dbtype == "sqlite" ) {
      //build an array of results
      $query  = @sqlite_query( $this->dbh, $sql );
      $icount = 0;
      switch ( $rowtype ) {
        case 0: //Object
          while ( $row = @sqlite_fetch_object( $query ) ) {
            $result[$icount] = $row;
            $icount++;
          }
          break;
        case 1: //Index
          while ( $row = @sqlite_fetch_array( $query, SQLITE_NUM ) ) {
            $result[$icount] = $row;
            $icount++;
          }
          break;
        case 2: //Associative Index
          while ( $row = @sqlite_fetch_array( $query, SQLITE_ASSOC ) ) {
            $result[$icount] = $row;
            $icount++;
          }
          break;
      }
      $this->nooffields = @sqlite_num_fields( $query );
      for ( $i = 0; $i < $this->nooffields; $i++ ) {
        $this->fieldinfo[$i]["name"]  = @sqlite_field_name( $query, $i );
        $this->fieldinfo[$i]["alias"] = ucwords( strtolower( $this->fieldinfo[$i]["name"] ) );
        $this->fieldinfo[$i][1]       = $this->fieldinfo[$i]["name"];
        $this->fieldinfo[$i][0]       = $this->fieldinfo[$i]["alias"];
        //$this->fieldinfo[$i][2] = $column_size; //need to calculate this
        //$this->fieldinfo[$i][4] = $this->fieldinfo[$i]["type"];
      }
      $this->affectedrows = $icount;
    } else /*SQLite*/ if ( $this->dbtype == "sqlite3" ) {
      //build an array of results
      $query = $this->dbh->query( $sql );
      if ( !method_exists( $query, "fetchArray" ) ) {
        trigger_error( "Cant get row for $this->dbpath in " . __METHOD__ . " for " . $this->dbtype, E_USER_NOTICE );
      }
      $icount = 0;
      if ( $query ) {
        switch ( $rowtype ) {
          case 0: //Object
            while ( $temprow = $query->fetchArray( SQLITE3_ASSOC ) ) {
              //make the row an object
              unset( $row );
              foreach ( $temprow as $fieldname => $fieldvalue ) {
                $row->$fieldname = $fieldvalue;
              }
              $result[$icount] = $row;
              $icount++;
            }
            break;
          case 1: //Index
            while ( $row = $query->fetchArray( SQLITE3_NUM ) ) {
              $result[$icount] = $row;
              $icount++;
            }
            break;
          case 2: //Associative Index
            while ( $row = $query->fetchArray( SQLITE3_ASSOC ) ) {
              $result[$icount] = $row;
              $icount++;
            }
            break;
        }
        $nooffields = $query->numColumns();
        //print_r ($result);
        for ( $i = 0; $i < $nooffields; $i++ ) {
          $this->fieldinfo[$i]["name"]  = $query->columnName( $i );
          $this->fieldinfo[$i]["alias"] = ucwords( strtolower( $this->fieldinfo[$i]["name"] ) );
          eval( '$this->fieldinfo[$i]["type"] = $this->get_data_type ($result[0]->' . $query->columnName( $i ) . ');' );
          $this->fieldinfo[$i][1] = $this->fieldinfo[$i]["name"];
          $this->fieldinfo[$i][0] = $this->fieldinfo[$i]["alias"];
          //$this->fieldinfo[$i][2] = $column_size; //need to calculate this
          $this->fieldinfo[$i][4] = $this->fieldinfo[$i]["type"];
        }
        $this->affectedrows = $icount;
      }
    } else /*Firebird*/ if ( $this->dbtype == "firebird" ) {
      //build an array of results
      $query = @ibase_query( $this->dbh, $sql );
      if ( $query ) {
        $icount = 0;
        switch ( $rowtype ) {
          case 0: //Object
            while ( $row = @ibase_fetch_object( $query ) ) {
              $result[$icount] = $row;
              $icount++;
            }
            break;
          case 1: //Index
            while ( $row = @ibase_fetch_row( $query ) ) {
              $result[$icount] = $row;
              $icount++;
            }
            break;
          case 2: //Associative Index
            while ( $row = @ibase_fetch_assoc( $query ) ) {
              $result[$icount] = $row;
              $icount++;
            }
            break;
        }
      }
      $this->nooffields = @ibase_num_fields( $query );
      for ( $i = 0; $i < $this->nooffields; $i++ ) {
        $this->fieldinfo[$i] = @ibase_field_info( $query, $i );
        //print_r ($this->fieldinfo[$i]);
        if ( $this->fieldinfo[$i]["name"] == "" ) {
          $this->fieldinfo[$i][0]      = $this->fieldinfo[$i]["alias"];
          $this->fieldinfo[$i]["name"] = $this->fieldinfo[$i]["alias"];
        }
      }
      $this->affectedrows = $icount;
    } else /*Oracle*/ if ( $this->dbtype == "oracle" ) {
      //build an array of results
      $query = @oci_parse( $this->dbh, $sql );
      @oci_execute( $query );
      $icount = 0;
      switch ( $rowtype ) {
        case 0: //Object
          while ( $row = @oci_fetch_object( $query ) ) {
            $result[$icount] = $row;
            $icount++;
          }
          break;
        case 1: //Index
          while ( $row = @oci_fetch_row( $query ) ) {
            $result[$icount] = $row;
            $icount++;
          }
          break;
        case 2: //Associative Index
          while ( $row = @oci_fetch_array( $query, OCI_ASSOC ) ) {
            $result[$icount] = $row;
            $icount++;
          }
          break;
      }
    } else /*My SQL*/ if ( $this->dbtype == "mysql" ) {
      //build an array of results
      if ( function_exists( "mysqli_connect" ) ) {
        $query = $this->dbh->query( $sql );
        if ( is_object( $query ) ) {
          $fields               = $query->fetch_fields();
          $this->nooffields     = count( $fields );
          $mysql_data_type_hash = array(
             1 => 'INTEGER',
            2 => 'INTEGER',
            3 => 'INTEGER',
            4 => 'NUMERIC',
            5 => 'NUMERIC',
            7 => 'DATE',
            8 => 'INTEGER',
            9 => 'INTEGER',
            10 => 'DATE',
            11 => 'DATE',
            12 => 'DATE',
            13 => 'year',
            16 => 'bit',
            252 => 'BLOB',
            253 => 'VARCHAR',
            254 => 'VARCHAR',
            246 => 'NUMERIC' 
          );
          $i                    = 0;
          foreach ( $fields as $field ) {
            $this->fieldinfo[$i]["name"]   = strtoupper( $field->orgname );
            $this->fieldinfo[$i]["alias"]  = strtoupper( $field->name );
            $this->fieldinfo[$i]["length"] = $field->length;
            $this->fieldinfo[$i]["type"]   = $mysql_data_type_hash[$field->type];
            $this->fieldinfo[$i][0]        = strtoupper( $field->orgname );
            $this->fieldinfo[$i][1]        = strtoupper( $field->name );
            $this->fieldinfo[$i][2]        = $field->length;
            $this->fieldinfo[$i][3]        = $mysql_data_type_hash[$field->type];
            $i++;
          }
          $icount = 0;
          switch ( $rowtype ) {
            case 0: //Object
              while ( $row = $query->fetch_object() ) {
                $result[$icount] = $row;
                $icount++;
              }
              break;
            case 1: //Index
              while ( $row = $query->fetch_row() ) {
                $result[$icount] = $row;
                $icount++;
              }
              break;
            case 2: //Associative Index
              while ( $row = $query->fetch_assoc() ) {
                $result[$icount] = $row;
                $icount++;
              }
              break;
          }
          $this->affectedrows = $icount;
          //free up the query
          $query->close();
        } else {
          $this->lasterror[count( $this->lasterror )] = $this->dbh->error;
        }
      } else {
        $query  = @mysql_query( $sql );
        $icount = 0;
        switch ( $rowtype ) {
          case 0: //Object
            while ( $row = @mysql_fetch_object( $query ) ) {
              $result[$icount] = $row;
              $icount++;
            }
            break;
          case 1: //Index
            while ( $row = @mysql_fetch_row( $query ) ) {
              $result[$icount] = $row;
              $icount++;
            }
            break;
          case 2: //Associative Index
            while ( $row = @mysql_fetch_array( $query, mysql_ASSOC ) ) {
              $result[$icount] = $row;
              $icount++;
            }
            break;
        }
        $this->nooffields = @mysql_num_fields( $query );
        for ( $i = 0; $i < $this->nooffields; $i++ ) {
          $column_name                   = @mysql_field_name( $query, $i );
          $column_type                   = @mysql_field_type( $query, $i );
          $column_size                   = @mysql_field_len( $query, $i );
          $fieldinfo                     = @mysql_fetch_field( $query, $i );
          $this->fieldinfo[$i]["name"]   = strtoupper( $column_name );
          $this->fieldinfo[$i]["alias"]  = strtoupper( $column_name );
          $this->fieldinfo[$i]["length"] = $column_size;
          $this->fieldinfo[$i]["type"]   = strtoupper( $column_type );
          $this->fieldinfo[$i][1]        = strtoupper( $column_name );
          ;
          $this->fieldinfo[$i][0] = strtoupper( $column_name );
          ;
          $this->fieldinfo[$i][2] = $column_size;
          $this->fieldinfo[$i][4] = strtoupper( $column_type );
        }
        $this->affectedrows = $icount;
      }
    } else /*Postgres*/ if ( $this->dbtype == "postgres" ) {
      //build an array of results
      $query  = @pg_query( $this->dbh, $sql );
      $icount = 0;
      switch ( $rowtype ) {
        case 0: //Object
          while ( $row = @pg_fetch_object( $query ) ) {
            $result[$icount] = $row;
            $icount++;
          }
          break;
        case 1: //Index
          while ( $row = @pg_fetch_row( $query ) ) {
            $result[$icount] = $row;
            $icount++;
          }
          break;
        case 2: //Associative Index
          while ( $row = @pg_fetch_assoc( $query ) ) {
            $result[$icount] = $row;
            $icount++;
          }
          break;
      }
      $this->nooffields = @pg_num_fields( $query );
      for ( $i = 0; $i < $this->nooffields; $i++ ) {
        $column_name                   = @pg_field_name( $query, $i );
        $column_type                   = @pg_field_type( $query, $i );
        $column_size                   = @pg_field_size( $query, $i );
        $this->fieldinfo[$i]["name"]   = strtoupper( $column_name );
        $this->fieldinfo[$i]["alias"]  = strtoupper( $column_name );
        $this->fieldinfo[$i]["length"] = $column_size;
        $this->fieldinfo[$i]["type"]   = strtoupper( $column_type );
        $this->fieldinfo[$i][1]        = strtoupper( $column_name );
        ;
        $this->fieldinfo[$i][0] = strtoupper( $column_name );
        ;
        $this->fieldinfo[$i][2] = $column_size;
        $this->fieldinfo[$i][4] = strtoupper( $column_type );
      }
      $this->affectedrows = $icount;
    } else /*Microsoft SQL Server*/ if ( $this->dbtype == "mssql" ) {
      //build an array of results
      $query  = @mssql_query( $sql );
      //echo $sql;     
      $icount = 0;
      switch ( $rowtype ) {
        case 0: //Object
          while ( $row = @mssql_fetch_object( $query ) ) {
            $result[$icount] = $row;
            $icount++;
          }
          break;
        case 1: //Index
          while ( $row = @mssql_fetch_row( $query ) ) {
            $result[$icount] = $row;
            $icount++;
          }
          break;
        case 2: //Associative Index
          while ( $row = @mssql_fetch_assoc( $query ) ) {
            $result[$icount] = $row;
            $icount++;
          }
          break;
      }
      $this->nooffields = @mssql_num_fields( $query );
      for ( $i = 0; $i < $this->nooffields; $i++ ) {
        $column_name = @mssql_field_name( $query, $i );
        $column_type = @mssql_field_type( $query, $i );
        $column_size = @mssql_field_length( $query, $i );
        //code to determine aliases - testing in mssql - needs to be applied to all databases
        //by Rudy Smith
        $origalias   = explode( $column_name, $sql );
        $origcol     = explode( " as ", $origalias[0] );
        $lastone     = explode( " ", $origcol[count( $originalcol )] );
        $origcol     = $lastone[count( $lastone ) - 1];
        if ( $origcol == '' ) {
          $origcol = strtoupper( $column_name );
        }
        $this->fieldinfo[$i]["name"]   = strtoupper( $origcol );
        //echo "<br>";
        $this->fieldinfo[$i]["alias"]  = strtoupper( $column_name );
        $this->fieldinfo[$i]["length"] = $column_size;
        $this->fieldinfo[$i]["type"]   = strtoupper( $column_type );
        $this->fieldinfo[$i][1]        = strtoupper( $column_name );
        ;
        $this->fieldinfo[$i][0] = strtoupper( $column_name );
        ;
        $this->fieldinfo[$i][2] = $column_size;
        $this->fieldinfo[$i][4] = strtoupper( $column_type );
      }
      $this->affectedrows = $icount;
    } else {
      trigger_error( "Please implement " . __METHOD__ . " for " . $this->dbtype, E_USER_ERROR );
    }
    //create the field information based on the select statement    
    foreach ( $this->fieldinfo as $id => $field ) {
      if ( strpos( strtoupper( $field[4] ), "NUMERIC" ) !== false || strpos( strtoupper( $field[4] ), "DECIMAL" ) !== false || strpos( strtoupper( $field[4] ), "INTEGER" ) !== false || strpos( strtoupper( $field[4] ), "INT" ) !== false ) {
        if ( strpos( strtoupper( $field[4] ), "NUMERIC" ) !== false || strpos( strtoupper( $field[4] ), "DECIMAL" ) !== false ) {
          $field[4]      = "CURRENCY";
          $field["type"] = "CURRENCY";
        }
        $field[5]       = "right";
        $field["align"] = "right";
      } else {
        $field[5]       = "left";
        $field["align"] = "left";
      }
      if ( $field[3] >= 200 ) {
        $field[6]            = 180;
        $field["htmllength"] = 180;
      } else if ( $field[3] <= 100 ) {
        $field[6]            = 120;
        $field["htmllength"] = 120;
      } else {
        $field[6]            = $field[3];
        $field["htmllength"] = $field[3];
      }
      $this->fieldinfo[$id] = $field;
    }
    $this->RAWRESULT = $result;
    
    //get the last error
    $this->get_error();
    
    /* Debugging for get_row */
    if ( $result ) {
      //check the data
      //Make the object uppercase for all the field names which is our standard convention 
      //We also need to give back the appropriate
      if ( $rowtype == 0 ) {
        $newresult = Array( );
        foreach ( $result as $id => $value ) {
          foreach ( $value as $field => $fieldvalue ) {
            $fieldinfo = $this->get_field_by_name( $field );
            if ( $fetchblob ) {
              if ( $this->dbtype == "CUBRID" && $fieldinfo["type"] == "BLOB" ) {
                //file:/root/CUBRID/databases/FILEOMINT/lob/ces_058/sw_script.00001335346225392229_6061
                $table     = explode( ".", $fieldvalue );
                $table     = explode( "/", $table[0] );
                $table     = $table[count( $table ) - 1];
                $fieldname = $this->fieldinfo[0]["name"];
                eval( '$fieldvalue = "select {$fieldinfo["name"]} from $table where {$this->fieldinfo[0]["name"]} = \'".$value->' . $fieldname . '."\'";' );
              }
              if ( $fieldinfo["type"] == "BLOB" || $fieldinfo["type"] == "OID" )
                $fieldvalue = $this->get_blob( $fieldvalue );
            }
            if ( ( $fieldinfo["type"] == "DATETIME" || $fieldinfo["type"] == "DATE" || $fieldinfo["type"] == "TIMESTAMP" ) && $this->dbtype != "firebird" ) { //firebird doesn't need to have any adjustments here
              if ( $this->dbtype == "mssqlnative" ) {
                foreach ( $fieldvalue as $name => $value ) {
                  if ( $name == "date" ) {
                    $adate = $value;
                  }
                }
                $fieldvalue = $this->translate_date( $adate, $this->dbdateformat, $this->outputdateformat );
                $adate      = "";
              } else {
                $fieldvalue = $this->translate_date( $fieldvalue, $this->dbdateformat, $this->outputdateformat );
              }
            }
            $field = strtoupper( $field );
            if ( isset( $calculatedfields[strtoupper( $fieldinfo["alias"] )] ) ) {
              $this->updatefieldinfo = false;
              eval( '$newresult[$id]->$field = ' . $calculatedfields[strtoupper( $fieldinfo["alias"] )] . ';' );
              $this->updatefieldinfo = true;
            } else {
              $newresult[$id]->$field = $fieldvalue;
            }
          }
        }
        $result = $newresult;
      } else if ( $rowtype == 1 ) //We can't leave this out because we need to read blobs - so the fieldnames are not made uppercase
        {
        $newresult = Array( );
        foreach ( $result as $id => $value ) {
          foreach ( $value as $field => $fieldvalue ) {
            $fieldinfo = $this->fieldinfo[$field];
            if ( $this->dbtype == "CUBRID" && $fieldinfo["type"] == "BLOB" ) {
              //file:/root/CUBRID/databases/FILEOMINT/lob/ces_058/sw_script.00001335346225392229_6061
              $table     = explode( ".", $fieldvalue );
              $table     = explode( "/", $table[0] );
              $table     = $table[count( $table ) - 1];
              $fieldname = $this->fieldinfo[0]["name"];
              eval( '$fieldvalue = "select {$fieldinfo["name"]} from $table where {$this->fieldinfo[0]["name"]} = \'".$value[0]."\'";' );
            }
            if ( $fetchblob ) {
              if ( $fieldinfo["type"] == "BLOB" || $fieldinfo["type"] == "OID" )
                $fieldvalue = $this->get_blob( $fieldvalue );
            }
            if ( ( $fieldinfo["type"] == "DATETIME" || $fieldinfo["type"] == "DATE" ) && $this->dbtype != "firebird" ) { //firebird doesn't need to have any adjustments here
              if ( $this->dbtype == "mssqlnative" ) {
                foreach ( $fieldvalue as $name => $value ) {
                  if ( $name == "date" ) {
                    $adate = $value;
                  }
                }
                $fieldvalue = $this->translate_date( $adate, $this->dbdateformat, $this->outputdateformat );
                $adate      = "";
              } else {
                $fieldvalue = $this->translate_date( $fieldvalue, $this->dbdateformat, $this->outputdateformat );
              }
            }
            if ( isset( $calculatedfields[strtoupper( $fieldinfo["alias"] )] ) ) {
              $this->updatefieldinfo = false;
              eval( '$newresult[$id][$field] = ' . $calculatedfields[strtoupper( $fieldinfo["alias"] )] . ';' );
              $this->updatefieldinfo = true;
            } else {
              $newresult[$id][$field] = $fieldvalue;
            }
          }
        }
        $result = $newresult;
      } else if ( $rowtype == 2 ) {
        $newresult = Array( );
        foreach ( $result as $id => $value ) {
          foreach ( $value as $field => $fieldvalue ) {
            $fieldinfo = $this->get_field_by_name( $field );
            if ( $this->dbtype == "CUBRID" && $fieldinfo["type"] == "BLOB" ) {
              //file:/root/CUBRID/databases/FILEOMINT/lob/ces_058/sw_script.00001335346225392229_6061
              $table     = explode( ".", $fieldvalue );
              $table     = explode( "/", $table[0] );
              $table     = $table[count( $table ) - 1];
              $fieldname = $this->fieldinfo[0]["name"];
              eval( '$fieldvalue = "select {$fieldinfo["name"]} from $table where {$this->fieldinfo[0]["name"]} = \'".$value[$fieldname]."\'";' );
            }
            if ( $fetchblob ) {
              if ( $fieldinfo["type"] == "BLOB" || $fieldinfo["type"] == "OID" )
                $fieldvalue = $this->get_blob( $fieldvalue );
            }
            if ( ( $fieldinfo["type"] == "DATETIME" || $fieldinfo["type"] == "DATE" ) && $this->dbtype != "firebird" ) { //firebird doesn't need to have any adjustments here
              if ( $this->dbtype == "mssqlnative" ) {
                foreach ( $fieldvalue as $name => $value ) {
                  if ( $name == "date" ) {
                    $adate = $value;
                  }
                }
                $fieldvalue = $this->translate_date( $adate, $this->dbdateformat, $this->outputdateformat );
                $adate      = "";
              } else {
                $fieldvalue = $this->translate_date( $fieldvalue, $this->dbdateformat, $this->outputdateformat );
              }
            }
            $field = strtoupper( $field );
            if ( isset( $calculatedfields[strtoupper( $fieldinfo["alias"] )] ) ) {
              $this->updatefieldinfo = false;
              eval( '$newresult[$id][$field] = ' . $calculatedfields[strtoupper( $fieldinfo["alias"] )] . ';' );
              $this->updatefieldinfo = true;
            } else {
              $newresult[$id][$field] = $fieldvalue;
            }
          }
        }
        
        
        
        
        $result = $newresult;
        
      }
    } else {
      trigger_error( "Cant get row for $this->dbpath in " . __METHOD__ . " for " . $this->dbtype, E_USER_NOTICE );
    }
    if ( !$this->updatefieldinfo ) {
      $this->fieldinfo = $tempfieldinfo;
    }
    return $result;
  }
  /* 
  END  Get Row
  *****************************************************************************/
  /***************************************************************************** 
  BEGIN Translate date - Change a date from the data to output format specified in the connection file
  */
  function translate_date( $input, $dbdateformat, $outputdateformat ) {
    //mssql returns dates as a object
    if ( $input != "" && !is_object( $input ) ) {
      $split             = explode( " ", $input ); //get date and minutes
      $result            = explode( " ", $outputdateformat );
      $result            = $result[0]; //just the date part
      $YYto              = strpos( $outputdateformat, "YYYY" );
      $YYin              = strpos( $dbdateformat, "YYYY" );
      $mmto              = strpos( $outputdateformat, "mm" );
      $mmin              = strpos( $dbdateformat, "mm" );
      $ddto              = strpos( $outputdateformat, "dd" );
      $ddin              = strpos( $dbdateformat, "dd" );
      $result[$mmto]     = $input[$mmin];
      $result[$mmto + 1] = $input[$mmin + 1];
      $result[$ddto]     = $input[$ddin];
      $result[$ddto + 1] = $input[$ddin + 1];
      $result[$YYto]     = $input[$YYin];
      $result[$YYto + 1] = $input[$YYin + 1];
      $result[$YYto + 2] = $input[$YYin + 2];
      $result[$YYto + 3] = $input[$YYin + 3];
      $result .= " " . $split[1]; //add the time piece to the end
      $result = trim( $result );
    } else {
      $result = "";
    }
    return $result;
  }
  /* 
  END  Translate date
  *****************************************************************************/
  /***************************************************************************** 
  BEGIN Get Blob - Get the data from a blob like in Firebird - System function
  */
  function get_blob( $column ) {
    $content = "";
    if ($column && $this->dbtype == "CUBRID") {
      $req = @cubrid_execute( $this->dbh, $column );
      $row = @cubrid_fetch_row( $req, CUBRID_LOB );
      $content = "";
      while (true) {
        if ($data = cubrid_lob2_read( $row[0], 1024 ) ) {
          $content .= $data;
        }
        elseif ( $data === false ) {
            break;
        }
        else {
            break;
        }             
      }
    } else if ( $column && $this->dbtype == "firebird" ) {
      //Get the blob information
      $blob_data = ibase_blob_info( $this->dbh, $column );
      //Get a handle to the blob
      $blob_hndl = ibase_blob_open( $this->dbh, $column );
      //Get the blob contents
      $content   = ibase_blob_get( $blob_hndl, $blob_data[0] );
    } else if ( $column && $this->dbtype == "postgres" ) {
      //This may kill performance finding the size of the blob - but how else ???
      pg_query( $this->dbh, "begin" );
      $handle = pg_lo_open( $this->dbh, $column, "r" );
      //Find the end of the blob
      pg_lo_seek( $handle, 0, PGSQL_SEEK_END );
      $size = pg_lo_tell( $handle );
      //Find the beginning of the blob
      pg_lo_seek( $handle, 0, PGSQL_SEEK_SET );
      //Read the whole blob
      $content = pg_lo_read( $handle, $size );
      pg_query( $this->dbh, "commit" );
    } else //All other databases ???
      {
      $content = $column;
    }
    return $content;
  }
  /* 
  END  Get Blob
  *****************************************************************************/
  /***************************************************************************** 
  BEGIN Set Blob - Set the data to a blob like in Firebird, MySQL, Postgres
  */
  function set_blob( $tablename, $column, $blobvalue, $filter = "fieldname = 0" ) {
    $result = "";
    if ( $column && $this->dbtype == "odbc" ) {
      $sqlupdate = "update $tablename set $column = ? where $filter";
      $result    = $this->exec( $sqlupdate, $blobvalue );
    } else if ( $column && $this->dbtype == "CUBRID" ) {
      $sqlupdate = "update $tablename set $column = ? where $filter";
      $result    = $this->exec( $sqlupdate, $blobvalue );
    } else if ( $column && $this->dbtype == "sqlite" ) {
      $sqlupdate = "update $tablename set $column = '" . sqlite_escape_string( $blobvalue ) . "' where $filter";
      $result    = $this->exec( $sqlupdate );
    } else if ( $column && $this->dbtype == "sqlite3" ) {
      $sqlupdate = "update $tablename set $column = :blob where $filter";
      $query     = $this->dbh->prepare( $sqlupdate );
      $query->bindValue( ":blob", $blobvalue, SQLITE3_BLOB );
      $result = $query->execute();
    } else if ( $column && $this->dbtype == "firebird" ) {
      $sqlupdate = "update $tablename set $column = ? where $filter";
      $result    = $this->exec( $sqlupdate, $blobvalue );
    } else if ( $column && $this->dbtype == "mysql" ) {
      if ( function_exists( "mysqli_connect" ) ) {
        $sqlupdate = "update $tablename set $column = ? where $filter";
        $query     = $this->dbh->prepare( $sqlupdate );
        $null      = NULL;
        $query->bind_param( "b", $null );
        $query->send_long_data( 0, $blobvalue );
        $query->execute();
      } else {
        $sqlupdate = "update $tablename set $column = 0x" . bin2hex( $blobvalue ) . " where $filter";
        $result    = $this->exec( $sqlupdate );
      }
    } else if ( $column && $this->dbtype == "postgres" ) {
      pg_query( $this->dbh, "begin" );
      $oid    = pg_lo_create( $this->dbh );
      $handle = pg_lo_open( $this->dbh, $oid, "w" );
      pg_lo_write( $handle, $blobvalue );
      pg_lo_close( $handle );
      pg_query( $this->dbh, "commit" );
      $sqlupdate = "update $tablename set $column = '$oid' where $filter";
      $result    = $this->exec( $sqlupdate );
    } else if ( $column && $this->dbtype == "mssql" || $this->dbtype == "mssqlnative" ) {
      $sqlupdate = "update $tablename set $column = 0x" . bin2hex( $blobvalue ) . " where $filter";
      $result    = $this->exec( $sqlupdate );
    }
    return $result;
  }
  /* 
  END  Set Blob
  *****************************************************************************/
  /***************************************************************************** 
  BEGIN Get Value - Fetch a row in a number of formats
  */
  function get_value( $id = 0, $sql = "", $rowtype = 0, $fetchblob = true ) {
    $result = false;
    //Dont matter if there is no sql - use the last one.
    if ( $sql == "" )
      $sql = $this->lastsql[count( $this->lastsql ) - 1];
    $sql    = $this->parsesql( $sql );
    $result = $this->get_row( $sql, $rowtype, $fetchblob );
    $result = $result[$id]; //return the first value
    return $result;
  }
  /* 
  END  Get Value
  *****************************************************************************/
  
  /***************************************************************************** 
  BEGIN Get Record - Fetch a row in a number of formats with prefix
  */
  function get_record($sql = "", $prefix="", $rowtype = 0, $fetchblob = true ) {
    $result = false;
    //Dont matter if there is no sql - use the last one.
    if ( $sql == "" )
      $sql = $this->lastsql[count( $this->lastsql ) - 1];
    
    $sql    = $this->parsesql( $sql );
    $result = $this->get_row( $sql, $rowtype, $fetchblob );
    $result = $result[0]; //return the first value
    
    
    if ($prefix != "") {
      $newobject = null;
      foreach ($result as $key => $value) {
          $newkey = $prefix.$key;
          //check for serialized data
          if (unserialize($value) !== false) {
            $value = unserialize ($value);
          }
          $newobject->$newkey = $value;
      }
      
      if ($rowtype == 1 || $rowtype == 2 ) {
        $result = (array) $newobject;
      }
       else {
        $result = $newobject;
      }    
    }
        
    return $result;
  }
  /* 
  END  Get Record
  *****************************************************************************/
  
  
  /***************************************************************************** 
  BEGIN Get Records - Fetch a row in a number of formats with prefix
  */
  function get_records($sql = "", $prefix="", $rowtype = 0, $fetchblob = true ) {
    $result = false;
    //Dont matter if there is no sql - use the last one.
    if ( $sql == "" )
      $sql = $this->lastsql[count( $this->lastsql ) - 1];
    
    $sql    = $this->parsesql( $sql );
    $result = $this->get_row( $sql, $rowtype, $fetchblob );
    
    $newresult = array();
    foreach ($result as $rid => $record ) {
      $aresult = $record; //return the first value
        $newobject = null;
        foreach ($aresult as $key => $value) {
          $newkey = $prefix.$key;
          if (unserialize($value) !== false) {
            $value = unserialize ($value);
          }
          $newobject->$newkey = $value;
        }
        $newresult[] = $newobject;    
    }
    
    //fix up the field info
    $fieldinfo = $this->fieldinfo;
    foreach ($fieldinfo as $fid => $field) {
      $this->fieldinfo[$fid]["name"] = $prefix.strtoupper ($fieldinfo[$fid]["name"]);
      $this->fieldinfo[$fid][1] = $fieldinfo[$fid]["name"];     
    }
    
    
           
    return $newresult;
  }
  /* 
  END  Get Records
  *****************************************************************************/
  
  
  
  
  /***************************************************************************** 
  BEGIN get_database
  Returns the layout of the whole database in an easy to use array
  
  Need to add support for views & stored procedures later on
  */
  function get_database( ) {
    $result   = false;
    $database = NULL;
    if ( !$this->dbh ) {
      trigger_error( "No database handle, use connect first in " . __METHOD__ . " for " . $this->dbtype, E_USER_WARNING );
    } else if ( $this->dbtype == "odbc" ) {
    } else if ( $this->dbtype == "CUBRID" ) {
      $sqltables = "SELECT class_name as name 
                    FROM db_class 
                    WHERE is_system_class = 'NO'
                    AND class_name <> '_cub_schema_comments'
                    ";
      $tables    = $this->get_row( $sqltables );
      foreach ( $tables as $id => $record ) {
        $sqlinfo   = "select * from $record->NAME limit 1";
        $tableinfo = $this->get_row( $sqlinfo );
        $fieldinfo = $this->fieldinfo;
        foreach ( $fieldinfo as $tid => $trecord ) {
          $database[trim( $record->NAME )][$tid]["column"]  = $tid;
          $database[trim( $record->NAME )][$tid]["field"]   = trim( $trecord["name"] );
          $database[trim( $record->NAME )][$tid]["type"]    = trim( $trecord["type"] );
          $database[trim( $record->NAME )][$tid]["default"] = "";
          $database[trim( $record->NAME )][$tid]["notnull"] = "";
          $database[trim( $record->NAME )][$tid]["pk"]      = "";
        }
      }
      $result = $database;
    } else /*SQLite & SQLite3 */ if ( $this->dbtype == "sqlite" || $this->dbtype == "sqlite3" ) {
      $sqltables = "select name 
                      from sqlite_master
                     where type='table'
                  order by name";
      $tables    = $this->get_row( $sqltables );
      foreach ( $tables as $id => $record ) {
        $sqlinfo   = "pragma table_info($record->NAME);";
        $tableinfo = $this->get_row( $sqlinfo );
        //Go through the tables and extract their column information
        foreach ( $tableinfo as $tid => $trecord ) {
          $database[trim( $record->NAME )][$tid]["column"]  = trim( $trecord->CID );
          $database[trim( $record->NAME )][$tid]["field"]   = trim( $trecord->NAME );
          $database[trim( $record->NAME )][$tid]["type"]    = trim( $trecord->TYPE );
          $database[trim( $record->NAME )][$tid]["default"] = trim( $trecord->DFLT_VALUE );
          $database[trim( $record->NAME )][$tid]["notnull"] = trim( $trecord->NOTNULL );
          $database[trim( $record->NAME )][$tid]["pk"]      = trim( $trecord->PK );
        }
      }
      $result = $database;
    } else /*Firebird*/ if ( $this->dbtype == "firebird" ) {
      $sqltables = 'select distinct rdb$relation_name as tablename
                      from rdb$relation_fields
                     where rdb$system_flag=0
                       and rdb$view_context is null';
      $tables    = $this->get_row( $sqltables );
      foreach ( $tables as $id => $record ) {
        $sqlinfo   = 'SELECT r.RDB$FIELD_NAME AS field_name,
                           r.RDB$DESCRIPTION AS field_description,
                           r.RDB$DEFAULT_VALUE AS field_default_value,
                           r.RDB$NULL_FLAG AS field_not_null_constraint,
                           f.RDB$FIELD_LENGTH AS field_length,
                           f.RDB$FIELD_PRECISION AS field_precision,
                           f.RDB$FIELD_SCALE AS field_scale,
                           CASE f.RDB$FIELD_TYPE
                              WHEN 261 THEN \'BLOB\'
                              WHEN 14 THEN \'CHAR\'
                              WHEN 40 THEN \'CSTRING\'
                              WHEN 11 THEN \'D_FLOAT\'
                              WHEN 27 THEN \'DOUBLE\'
                              WHEN 10 THEN \'FLOAT\'
                              WHEN 16 THEN \'INT64\'
                              WHEN 8 THEN \'INTEGER\'
                              WHEN 9 THEN \'QUAD\'
                              WHEN 7 THEN \'SMALLINT\'
                              WHEN 12 THEN \'DATE\'
                              WHEN 13 THEN \'TIME\'
                              WHEN 35 THEN \'TIMESTAMP\'
                              WHEN 37 THEN \'VARCHAR\'
                              ELSE \'UNKNOWN\'
                            END AS field_type,
                            f.RDB$FIELD_SUB_TYPE AS field_subtype,
                            coll.RDB$COLLATION_NAME AS field_collation,
                            cset.RDB$CHARACTER_SET_NAME AS field_charset
                       FROM RDB$RELATION_FIELDS r
                       LEFT JOIN RDB$FIELDS f ON r.RDB$FIELD_SOURCE = f.RDB$FIELD_NAME
                       LEFT JOIN RDB$COLLATIONS coll ON r.RDB$COLLATION_ID = coll.RDB$COLLATION_ID
                        AND f.RDB$CHARACTER_SET_ID = coll.RDB$CHARACTER_SET_ID
                       LEFT JOIN RDB$CHARACTER_SETS cset ON f.RDB$CHARACTER_SET_ID = cset.RDB$CHARACTER_SET_ID
                      WHERE r.RDB$RELATION_NAME = \'' . $record->TABLENAME . '\'
                    ORDER BY r.RDB$FIELD_POSITION';
        $tableinfo = $this->get_row( $sqlinfo );
        //Go through the tables and extract their column information
        foreach ( $tableinfo as $tid => $trecord ) {
          $database[trim( $record->TABLENAME )][$tid]["column"]      = $tid;
          $database[trim( $record->TABLENAME )][$tid]["field"]       = trim( $trecord->FIELD_NAME );
          $database[trim( $record->TABLENAME )][$tid]["description"] = trim( $trecord->FIELD_DESCRIPTION );
          $database[trim( $record->TABLENAME )][$tid]["type"]        = trim( $trecord->FIELD_TYPE );
          $database[trim( $record->TABLENAME )][$tid]["length"]      = trim( $trecord->FIELD_LENGTH );
          $database[trim( $record->TABLENAME )][$tid]["precision"]   = trim( $trecord->FIELD_PRECISION );
          $database[trim( $record->TABLENAME )][$tid]["default"]     = trim( $trecord->FIELD_DEFAULT_VALUE );
          $database[trim( $record->TABLENAME )][$tid]["notnull"]     = trim( $trecord->NOTNULL );
          $database[trim( $record->TABLENAME )][$tid]["pk"]          = trim( $trecord->PK );
        }
      }
      $result = $database;
    } else /* Oracle */ if ( $this->dbtype == "oracle" ) {
      $result = true;
    } else /*MySQL*/ if ( $this->dbtype == "mysql" ) {
      $dbpath    = explode( ":", $this->dbpath );
      $sqltables = "SELECT table_name, table_type, engine
                      FROM INFORMATION_SCHEMA.tables
                     WHERE upper(table_schema) = upper('{$dbpath[1]}')
                     ORDER BY table_type ASC, table_name DESC";
      $tables    = $this->get_row( $sqltables );
      foreach ( $tables as $id => $record ) {
        $sqlinfo   = 'show columns from ' . $record->TABLE_NAME;
        $tableinfo = $this->get_row( $sqlinfo );
        //Go through the tables and extract their column information
        foreach ( $tableinfo as $tid => $trecord ) {
          //split the length & type for field
          if ( strpos( $trecord->TYPE, "(" ) !== false ) {
            $type   = substr( $trecord->TYPE, 0, strpos( $trecord->TYPE, "(" ) );
            $length = substr( $trecord->TYPE, strpos( $trecord->TYPE, "(" ) + 1, strpos( $trecord->TYPE, ")" ) - strpos( $trecord->TYPE, "(" ) - 1 );
          } else {
            $type = $trecord->TYPE;
          }
          $database[trim( $record->TABLE_NAME )][$tid]["column"]      = $tid;
          $database[trim( $record->TABLE_NAME )][$tid]["field"]       = trim( $trecord->FIELD );
          $database[trim( $record->TABLE_NAME )][$tid]["description"] = trim( $trecord->EXTRA );
          $database[trim( $record->TABLE_NAME )][$tid]["type"]        = trim( $type );
          $database[trim( $record->TABLE_NAME )][$tid]["length"]      = trim( $length );
          $database[trim( $record->TABLE_NAME )][$tid]["precision"]   = "";
          $database[trim( $record->TABLE_NAME )][$tid]["default"]     = trim( $trecord->DEFAULT );
          $database[trim( $record->TABLE_NAME )][$tid]["notnull"]     = trim( $trecord->NULL );
          $database[trim( $record->TABLE_NAME )][$tid]["pk"]          = trim( $trecord->KEY );
        }
      }
      $result = $database;
    } else /*Postgres*/ if ( $this->dbtype == "postgres" ) {
      $dbpath    = explode( ":", $this->dbpath );
      $sqltables = "SELECT table_name
                      FROM INFORMATION_SCHEMA.tables
                     WHERE upper(table_catalog) = upper('{$dbpath[1]}')
                      AND upper(table_schema) = upper('public') 
                     ORDER BY table_type ASC, table_name DESC";
      $tables    = $this->get_row( $sqltables );
      foreach ( $tables as $id => $record ) {
        $sqlinfo   = "select * from INFORMATION_SCHEMA.columns where upper(table_name) = upper('$record->TABLE_NAME')";
        $tableinfo = $this->get_row( $sqlinfo );
        //Go through the tables and extract their column information
        foreach ( $tableinfo as $tid => $trecord ) {
          $database[trim( $record->TABLE_NAME )][$tid]["column"]      = $tid;
          $database[trim( $record->TABLE_NAME )][$tid]["field"]       = trim( strtoupper( $trecord->COLUMN_NAME ) );
          $database[trim( $record->TABLE_NAME )][$tid]["description"] = "";
          $database[trim( $record->TABLE_NAME )][$tid]["type"]        = trim( strtoupper( $trecord->UDT_NAME ) );
          $database[trim( $record->TABLE_NAME )][$tid]["length"]      = trim( strtoupper( $trecord->CHARACTER_MAXIMUM_LENGTH ) );
          $database[trim( $record->TABLE_NAME )][$tid]["precision"]   = trim( strtoupper( $trecord->NUMERIC_PRECISION ) );
          $default                                                    = explode( "::", $trecord->COLUMN_DEFAULT );
          $database[trim( $record->TABLE_NAME )][$tid]["default"]     = $default[0];
          $database[trim( $record->TABLE_NAME )][$tid]["notnull"]     = trim( strtoupper( $trecord->IS_NULLABLE ) );
          $database[trim( $record->TABLE_NAME )][$tid]["pk"]          = "";
        }
      }
      $result = $database;
    } else /*Microsoft SQL Server*/ if ( $this->dbtype == "mssql" || $this->dbtype == "mssqlnative" ) {
      $tables = $this->get_row( "sp_tables @table_type = \"'table'\"" );
      foreach ( $tables as $id => $record ) {
        $columns = $this->get_row( "sp_columns $record->TABLE_NAME" );
        foreach ( $columns as $tid => $trecord ) {
          $database[trim( $record->TABLE_NAME )][$tid]["column"]      = $tid;
          $database[trim( $record->TABLE_NAME )][$tid]["field"]       = trim( strtoupper( $trecord->COLUMN_NAME ) );
          $database[trim( $record->TABLE_NAME )][$tid]["description"] = trim( strtoupper( $trecord->REMARKS ) );
          $database[trim( $record->TABLE_NAME )][$tid]["type"]        = trim( strtoupper( $trecord->TYPE_NAME ) );
          $database[trim( $record->TABLE_NAME )][$tid]["length"]      = trim( strtoupper( $trecord->LENGTH ) );
          $database[trim( $record->TABLE_NAME )][$tid]["precision"]   = trim( strtoupper( $trecord->PRECISION ) );
          $database[trim( $record->TABLE_NAME )][$tid]["default"]     = "";
          $database[trim( $record->TABLE_NAME )][$tid]["notnull"]     = trim( strtoupper( $trecord->IS_NULLABLE ) );
          $database[trim( $record->TABLE_NAME )][$tid]["pk"]          = "";
        }
      }
      $result = $database;
    } else {
      trigger_error( "Please implement " . __METHOD__ . " for " . $this->dbtype, E_USER_ERROR );
    }
    /* Debugging for Close */
    if ( $result ) {
      //check the data
    } else {
      trigger_error( "Cant extract metadata from $this->dbpath in " . __METHOD__ . " for " . $this->dbtype, E_USER_NOTICE );
    }
    return $result;
  }
  /* 
  END  Get Database
  *****************************************************************************/
  /***************************************************************************** 
  BEGIN Get Field Info - Fetch basic field info
  
  result = Array (
  ["alias"] = Alias
  ["name"] = Name
  ["type"] = Generic field type
  ["width"] = Column width
  )
  
  */
  function get_field_info( $sql = "" ) {
    $result = 0;
    if ( $sql == "" ) {
      $result = $this->fieldinfo;
    } else {
      $this->get_row( $sql );
      $result = $this->fieldinfo;
    }
    return $result;
  }
  /* 
  END  Get Field Info
  *****************************************************************************/
  /***************************************************************************** 
  BEGIN Get Affected Rows
  Get the number of rows changed or retrieved by last SQL
  */
  function get_affected_rows( $sql = "" ) {
    $result = 0;
    if ( $sql == "" ) {
      $result = $this->affectedrows;
    } else {
      $this->get_row( $sql );
      $result = $this->affectedrows;
    }
    return $result;
  }
  /* 
  END  Get Affected Rows
  *****************************************************************************/
  /***************************************************************************** 
  BEGIN Get Field Info By Name
  Get a fields info by name
  */
  function get_field_by_name( $fieldname = "" ) {
    foreach ( $this->fieldinfo as $id => $value ) {
      if ( strtoupper( $fieldname ) == strtoupper( $value["alias"] ) ) {
        return $value;
        break;
      }
    }
    return null;
  }
  /* 
  END  Get Field By Name
  *****************************************************************************/
  /***************************************************************************** 
  BEGIN Get Random ID
  Get a random id function and adding 1
  */
  function get_random_id( $noofchars ) {
    $result = "";
    $result = hash( 'ripemd160', rand( 100000, 9999999 ) );
    $result = substr( $result, 0, $noofchars - 1 );
    return $result;
  }
  /* 
  END  Get Random Id
  *****************************************************************************/
  /***************************************************************************** 
  BEGIN Get Next ID
  Get the next id on a table by using the MAX function and adding 1
  */
  function get_next_id( $tablename = "", $fieldname = "", $filter = "" ) {
    $result = "";
    if ( $filter != "" )
      $filter = " where $filter"; //we may need to filter our tables
    $sql = "select max($fieldname)+1 as \"NEXTID\" from $tablename $filter";
    $sql = $this->parsesql( $sql );
    $row = $this->get_value( 0, $sql );
    $row = $this->RAWRESULT[0];
    if ( $row->NEXTID == "" ) {
      $row->NEXTID = 0;
    }
    //echo "<pre>".print_r ($this->RAWRESULT, 1)." {$sql} - ".print_r ($this->fieldinfo, 1). "\n</pre>";
    $result = $row->NEXTID;
    return $result;
  }
  /* 
  END  Get Next Id
  *****************************************************************************/
  /***************************************************************************** 
  BEGIN get_table_exists()
  See if a certain table exists
  */
  function get_table_exists( $tablename = "" ) {
    $result = "";
    $result = $this->get_database();
    if ( $result[$tablename] ) {
      return true;
    } else {
      return false;
    }
  }
  /* 
  END  Get Next Id
  *****************************************************************************/
  /***************************************************************************** 
  BEGIN Date to DB
  This function will format the date as needed by the database
  */
  function date_to_db( $invalue ) {
    //echo $invalue."<br>";
    if ( $invalue != "" ) //works only for firebird currently
      {
      $avalue = $this->translate_date( $invalue, $this->outputdateformat, $this->dbdateformat );
      return $avalue;
    } else {
      return "null";
    }
  }
  /* 
  END  Date to DB
  *****************************************************************************/
  /***************************************************************************** 
  BEGIN Get Insert SQL
  This function attempts to eliminate errors by creating the insert statements using prefixed input fields
  If you have a form with inputs prefixed with "txt" for example it will chop off the txt and make an insert statement
  Field names need to be in uppercase for better processing
  
  <form>
  <input type="text" name="txtNAME" value="Andre">
  <input type="text" name="txtDATE" value="01/10/2010">       
  </form>
  
  */
  function get_insert_sql( $fieldprefix = "edt", //Field prefix as discussed above 
    $tablename = "", //Name of the tablename
    $primarykey = "", //Field name of the primary key
    $genkey = true, //Generate a new number using inbuilt get_next_id 
    $requestvar = "", //Request variable to populate with new id for post processing
    $passwordfields = "", //Fields that may be crypted automatically
    $datefields = "", //Fields that may be seen as date fields and converted accordingly
    $exec = false,
    $arrayindex = 0 )  
    {
    //Get the length of field prefix
    $prefixlen = strlen( $fieldprefix );
    //Start the insert statement      
    if ( $genkey ) {
      $newid                 = $this->get_next_id( $tablename, $primarykey );
      $_REQUEST[$requestvar] = $newid;
      $sqlinsert             = "insert into $tablename ($primarykey";
    } else {
      $newid                 = $_REQUEST[$fieldprefix . strtoupper( $primarykey )];
      $_REQUEST[$requestvar] = $newid;
      $sqlinsert             = "insert into $tablename (";
    }
    //Search all the fields on the form
    foreach ( $_REQUEST as $name => $value ) {
      if ( substr( $name, 0, $prefixlen ) == $fieldprefix ) {
        $sqlinsert .= ", " . strtoupper( substr( $name, $prefixlen, strlen( $name ) - $prefixlen ) );
      }
    }
    //Check if must add the generated primary key value  
    if ( $genkey ) {
      $sqlinsert .= ") values ($newid";
    } else {
      $sqlinsert .= ") values (";
    }
    foreach ( $_REQUEST as $name => $value ) {
      if ( substr( $name, 0, $prefixlen ) == $fieldprefix ) {
        //if ($value == "on") $value = 1;
        //$value = stripcslashes ($value);
        if (is_array ($value)) {
          $value = $value[$arrayindex];
        }
        $value      = str_replace( "'", "''", $value );
        $tempfields = explode( ",", $passwordfields );
        foreach ( $tempfields as $id => $fieldname ) //Look for password fields
          {
          if ( $name == $fieldprefix . strtoupper( $fieldname ) ) {
            $value = crypt( $value );
          }
        }
        $tempfields = explode( ",", $datefields );
        foreach ( $tempfields as $id => $fieldname ) //Look for date fields and convert them
          {
          if ( $name == $fieldprefix . strtoupper( $fieldname ) ) {
            $value = $this->date_to_db( $value );
          }
        }
        $sqlinsert .= ", '" . $value . "'";
      }
    }
    $sqlinsert .= ")";
    //Clean up the sql
    $sqlinsert = str_replace( "(,", "(", $sqlinsert );
    $sqlinsert = str_replace( "'null'", "null", $sqlinsert );
    if ( !$exec ) //Do we run the procedure execution 
      {
      return $sqlinsert;
    } else {
      //Run the insert statement and upload files while we are at it.
      $this->exec( $sqlinsert );
      $error = $this->get_error();
      if ( $_FILES ) {
        foreach ( $_FILES as $name => $value ) {
          if ( $value["tmp_name"] != "" ) {
            if ( substr( $name, 0, $prefixlen ) == $fieldprefix ) {
              //upload the file correctly into a blob field
              $this->set_blob( $tablename, strtoupper( substr( $name, $prefixlen, strlen( $name ) - $prefixlen ) ), file_get_contents( $value["tmp_name"] ), $filter = "$primarykey = '" . $_REQUEST[$requestvar] . "'" );
            }
          }
        }
      }
      return $error;
    }
  }
  /* 
  END  Get Insert SQL
  *****************************************************************************/
  /***************************************************************************** 
  BEGIN Get Update SQL
  This function attempts to eliminate errors by creating the update statements using prefixed input fields
  If you have a form with inputs prefixed with "txt" for example it will chop off the txt and make an update statement
  Field names need to be in uppercase for better processing
  
  <form>
  <input type="text" name="txtNAME" value="Andre">
  <input type="text" name="txtDATE" value="01/10/2010">       
  </form>
  
  */
  function get_update_sql( $fieldprefix = "edt", //Field prefix as discussed above 
    $tablename = "", //Name of the tablename
    $primarykey = "", //Field name of the primary key
    $index = "", //Index 
    $requestvar = "", //Request variable to populate with new id for post processing
    $passwordfields = "", //Fields that may be crypted automatically
    $datefields = "",//Fields that may be seen as date fields and converted accordingly 
    $exec = false, //Execute the command immediately
    $arrayindex=0 //If a request field is an array - which index to use 
    )  
    {
    //Get the length of field prefix
    $prefixlen = strlen( $fieldprefix );
    $sqlupdate = "update $tablename set 0=0 ";
    foreach ( $_REQUEST as $name => $value ) {
      //we need to see if we are dealing with a multiple update
      if ( substr( $name, 0, $prefixlen ) == $fieldprefix ) {
        //print_r ($value);
        if (is_array ($value)) {
          $value = $value[$arrayindex];
        }
        
        if ( $value == "on" )
          $value = 1;
        $tempfields = explode( ",", $passwordfields );
        $dontupdate = false;
        foreach ( $tempfields as $id => $fieldname ) //Look for password fields
          {
          if ( $name == $fieldprefix . strtoupper( $fieldname ) ) {
            if ( $value != "" ) //only if there is a password do we encrypt it
              {
              $value = crypt( $value );
            } else {
              $dontupdate = true; //we must not update an empty password
            }
          }
        }
        $tempfields = explode( ",", $datefields );
        foreach ( $tempfields as $id => $fieldname ) //Look for date fields and convert them
          {
          if ( $name == $fieldprefix . strtoupper( $fieldname ) ) {
            $value = $this->date_to_db( $value );
          }
        }
        //$value = stripcslashes ($value);
        $value = str_replace( "'", "''", $value );
        if ( !$dontupdate ) {
          $sqlupdate .= ", " . strtoupper( substr( $name, $prefixlen, strlen( $name ) - $prefixlen ) ) . " = '" . $value . "'";
        }
      }
    }
    $sqlupdate .= " where $primarykey = '" . $index . "'";
    $sqlupdate                              = str_replace( "0=0 ,", "", $sqlupdate );
    $sqlupdate                              = str_replace( "'null'", "null", $sqlupdate );
    $this->lastsql[count( $this->lastsql )] = $sqlupdate;
    if ( !$exec ) //Do we run the procedure execution 
      {
      return $sqlupdate;
    } else {
      //Run the insert statement and upload files while we are at it.
      $this->exec( $sqlupdate );
      $error = $this->get_error();
      if ( $_FILES ) {
        foreach ( $_FILES as $name => $value ) {
          if ( $value["tmp_name"] != "" ) {
            if ( substr( $name, 0, $prefixlen ) == $fieldprefix ) {
              //upload the file correctly into a blob field
              $this->set_blob( $tablename, strtoupper( substr( $name, $prefixlen, strlen( $name ) - $prefixlen ) ), file_get_contents( $value["tmp_name"] ), $filter = "$primarykey = '" . $index . "'" );
            }
          }
        }
      }
      return $error;
    }
  }
  /* 
  END  Get Update SQL
  *****************************************************************************/
  
  function deploy_javascript () {
     $script = "function cde_calendar(caldiv, ainput, currentdate, startday, dateformat, canhide, extraevent) {
                  if(extraevent === undefined) extraevent = '';
                  var adiv = document.getElementById(caldiv);
                  while (adiv.hasChildNodes()) {adiv.removeChild(adiv.lastChild);}
                  var ainp = document.getElementById(ainput);
                  if (startday === undefined) startday = 0;
                  if (dateformat === undefined) dateformat = 'dd/mm/YYYY';
                  if (ainp.value !== '') currentdate = ainp.value;
                  var dd = parseInt(currentdate.substring(dateformat.indexOf('dd'), dateformat.indexOf('dd') + 2));
                  var mm = parseInt(currentdate.substring(dateformat.indexOf('mm'), dateformat.indexOf('mm') + 2)) - 1;
                  var YYYY = parseInt(currentdate.substring(dateformat.indexOf('YYYY'), dateformat.indexOf('YYYY') + 4));
                  var cdate = new Date(YYYY, mm, dd);
                  var bdate = new Date(YYYY, mm, 1);
                  var days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                  var months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                  var sday = bdate.getDay();
                  var offset = sday - startday;
                  var startdate = new Date(bdate);
                  startdate.setDate(bdate.getDate() - offset);
                  var ahtml = '';
                  var icountrow = 0;
                  var irows = 6;
                  var inextmonth = cdate.getMonth() + 1;
                  var ilastmonth = cdate.getMonth() - 1;
                  var inextyear = cdate.getFullYear();
                  var ilastyear = cdate.getFullYear();
                  if (ilastmonth < 0) {
                      ilastmonth = 11;
                      ilastyear = ilastyear - 1;
                  }
                  if (inextmonth > 11) {
                      inextmonth = 0;
                      inextyear = inextyear + 1;
                  }
                  var lastmonth = new Date(ilastyear, ilastmonth, 1);
                  var ldd = String(\"00\" + lastmonth.getDate()).slice(-2);
                  var lmm = String(\"00\" + (lastmonth.getMonth() + 1)).slice(-2);
                  var lYYYY = String(\"0000\" + (lastmonth.getFullYear())).slice(-4);
                  var ldate = dateformat;
                  ldate = ldate.replace('dd', ldd);
                  ldate = ldate.replace('mm', lmm);
                  ldate = ldate.replace('YYYY', lYYYY);
                  var nextmonth = new Date(inextyear, inextmonth, 1);
                  var ndd = String(\"00\" + nextmonth.getDate()).slice(-2);
                  var nmm = String(\"00\" + (nextmonth.getMonth() + 1)).slice(-2);
                  var nYYYY = String(\"0000\" + (nextmonth.getFullYear())).slice(-4);
                  var ndate = dateformat;
                  ndate = ndate.replace('dd', ndd);
                  ndate = ndate.replace('mm', nmm);
                  ndate = ndate.replace('YYYY', nYYYY);
                  var navnext = 'document.getElementById (\'' + ainput + '\').value = \'' + ndate + '\'; cde_calendar(\'' + caldiv + '\', \'' + ainput + '\', \'' + ndate + '\', ' + startday + ', \'' + dateformat + '\', false);';
                  var navprev = 'document.getElementById (\'' + ainput + '\').value = \'' + ldate + '\'; cde_calendar(\'' + caldiv + '\', \'' + ainput + '\', \'' + ldate + '\', ' + startday + ', \'' + dateformat + '\', false);';
                  var navcurr = 'cde_calendar(\'' + caldiv + '\', \'' + ainput + '\', \'' + currentdate + '\', ' + startday + ', \'' + dateformat + '\', false);';
                  //make a header
                  ahtml += '<div style=\"float:left;\"><a href=\"javascript:void(0)\" onclick=\"' + navprev + '\"> < </a></div><div style=\"float:left;\" class=\"header\">' + months[cdate.getMonth()] + ' ' + YYYY + '</div><div style=\"float:left;\"><a href=\"javascript:void(0)\" onclick=\"' + navnext + '\"> > </a></div>';
                  ahtml += '<br style=\"clear:both\" />';
                  var iweekday = startday;
                  for (var icount = 0; icount < 7; icount++) {
                      ahtml += '<div style=\"float:left;\">' + days[iweekday].substring(0, 2) + '</div>';
                      if (iweekday + 1 < 7) {
                          iweekday++
                      } else {
                          iweekday = 0;
                      }
                  }
                  ahtml += '<br style=\"clear:both\" />';
                  while (icountrow < irows) {
                      for (var icount = 0; icount < 7; icount++) {
                          var add = String(\"00\" + startdate.getDate()).slice(-2);
                          var amm = String(\"00\" + (startdate.getMonth() + 1)).slice(-2);
                          var aYYYY = String(\"0000\" + (startdate.getFullYear())).slice(-4);
                          var adate = dateformat;
                          adate = adate.replace('dd', add);
                          adate = adate.replace('mm', amm);
                          adate = adate.replace('YYYY', aYYYY);
                          var aclass = '';
                          if (startdate.getMonth() != cdate.getMonth()) {
                              aclass = 'othermonth';
                          } else {
                              aclass = 'thismonth';
                          }
                          if (startdate.getDate() == cdate.getDate() && startdate.getMonth() == cdate.getMonth()) {
                              aclass = 'currday';
                          }
                          ahtml += '<div style=\"float:left;\" class=\"' + aclass + '\"><a href=\"javascript:void(0)\" onclick=\"document.getElementById(\'' + caldiv + '\').innerHTML = \'\'; document.getElementById(\'' + ainput + '\').value =\'' + adate + '\'; '+extraevent+' \">' + startdate.getDate() + '</a></div>';
                          startdate.setDate(startdate.getDate() + 1);
                      }
                      ahtml += '<br style=\"clear:both\" />';
                      icountrow++;
                  }
                  adiv.innerHTML = ahtml;
                  adiv.style.left = ainp.offsetLeft;
                  if (canhide) adiv.style.display = 'none';
                  ainp.value = currentdate;
                  eval('ainp.addEventListener (\'click\', function (e) {  document.getElementById(\'' + caldiv + '\').style.display = \'\';  ' + navcurr + '  }, false);');
                  ainp = null;
                  adiv = null;
              }";
        
        $path = __DIR__."/cdescript/";
        $filename = $path."cde.js";
        if (!file_exists($filename)) {
           mkdir ($path, 0755, true);
           file_put_contents ($filename, $script);
        }
  
  }
  
  /***************************************************************************** 
  BEGIN Template
  Template function to add your own things
  */
  function template( ) {
    $result = false;
    if ( !$this->dbh ) {
      trigger_error( "No database handle, use connect first in " . __METHOD__ . " for " . $this->dbtype, E_USER_WARNING );
    } else /*SQLite*/ if ( $this->dbtype == "sqlite" ) {
      $result = true;
    } else /*Firebird*/ if ( $this->dbtype == "firebird" ) {
      $result = true;
    } else /* Oracle */ if ( $this->dbtype == "oracle" ) {
      $result = true;
    } else /*MySQL*/ if ( $this->dbtype == "mysql" ) {
      $result = true;
    } else /*Postgres*/ if ( $this->dbtype == "postgres" ) {
      $result = true;
    } else /*Microsoft SQL Server*/ if ( $this->dbtype == "mssql" ) {
      $result = true;
    } else {
      trigger_error( "Please implement " . __METHOD__ . " for " . $this->dbtype, E_USER_ERROR );
    }
    /* Debugging for Close */
    if ( $result ) {
      $this->dbh = "";
    } else {
      trigger_error( "Cant close $this->dbpath in " . __METHOD__ . " for " . $this->dbtype, E_USER_NOTICE );
    }
    return $result;
  }
  /* 
  END  Close
  *****************************************************************************/
}
/*CDE PDF CLASS*/
if ( file_exists( "fpdf/fpdf.php" ) ) {
  require_once( "fpdf/fpdf.php" );
  class CDEFPDF extends FPDF {
    public $reporttitle;
    public $extraheader;
    public $company;
    public $fields;
    public $params;
    public $groupby;
    public $oldgroup;
    public $columns;
    public $orientation;
    public $font;
    public $heading;
    public $line;
    public $columntotals;
    public $globaltotals;
    public $customheader;
    public $DB; //database connection
    public $records; //Database fields
    public $csvfile;
    public $csvdelim;
    //Draw Line
    function drawline( ) {
      $this->Ln();
      $this->Cell( 0, 0, "", 1, 0, "C" );
      $this->Ln();
    }
    //Create header
    function createheader( ) {
      $columns     = $this->columns;
      $hidecolumns = $this->hidecolumns;
      $left        = 0;
      $y           = $this->GetY() + 1;
      $x           = 10;
      $imaxy       = 0;
      for ( $i = 0; $i < count( $columns ); $i++ ) {
        if ( $columns[$i]["align"] == "left" ) {
          $align = "L";
        } else {
          $align = "R";
        }
        $left = $columns[$i]["ratiowidth"];
        if ( strtolower( $columns[$i]["alias"] ) != strtolower( $this->groupby ) && $hidecolumns[strtolower( $columns[$i]["alias"] )] != 1 ) {
          $this->SetY( $y );
          $this->SetX( $x );
          $this->SetFont( $this->font, 'B', $this->heading );
          $this->MultiCell( $left, 3, ucwords( strtolower( $columns[$i]["alias"] ) ), 0, "$align" );
          if ( $this->GetY() > $imaxy ) {
            $imaxy = $this->GetY();
          }
          $this->csvfile .= '"' . ucwords( strtolower( $columns[$i]["alias"] ) ) . '"' . $this->csvdelim;
          $x += $left;
        }
      }
      $this->csvfile = substr( $this->csvfile, 0, -1 ) . "\n";
      $this->SetY( $imaxy - 2 );
      $this->SetX( 10 );
      $this->drawline();
    }
    //Page header
    function Header( ) {
      //Arial bold 14
      $this->SetFont( $this->font, 'B', 12 );
      //Move to the right
      //Title
      $this->Cell( 0, 5, $this->company, 0, 0, 'L' );
      $this->SetFont( $this->font, 'BI', 14 );
      $this->SetTextColor( 200, 200, 200 );
      $this->Cell( 0, 5, "Confidential", 0, 0, 'R' );
      if ( function_exists( $this->extraheader ) ) {
        $this->Ln( 6 );
        $this->SetTextColor( 0, 0, 0 );
        $this->SetFont( $this->font, 'B', 10 );
        $params = Array(
           $this 
        );
        call_user_func_array( $this->extraheader, $params );
      } else {
        $this->Ln( 6 );
        $this->SetFont( $this->font, 'B', 10 );
        $this->SetTextColor( 0, 0, 0 );
        $this->Cell( 0, 5, $this->reporttitle, 0, 0, 'L' );
      }
      $this->drawline();
      $this->createheader();
    }
    //Page footer
    function Footer( ) {
      //Position at 1.5 cm from bottom
      $this->SetY( -15 );
      $this->drawline();
      $this->SetFont( $this->font, "", 7 );
      //Page number
      $this->Cell( 0, 5, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'L' );
      $this->SetX( 0 );
      $this->Cell( 0, 5, "developed by Spiceware Software (C)opyright CDE", 0, 0, 'C' );
      $this->Cell( 0, 5, date( "d/m/Y h:i:s" ), 0, 0, 'R' );
      $this->csvfile .= "Page " . $this->PageNo() . " developed by Spiceware Software (C)opyright CDE\n";
    }
    //create group header
    function creategroupby( $name, $formats, $hidecolumns ) {
      if ( $this->columntotals )
        $this->createfooter( false, $formats, $hidecolumns );
      $this->SetFont( $this->font, 'B', $this->heading + 2 );
      $this->Cell( 0, 5, $name, 0, 0, "L" );
      $this->Ln();
      $this->Cell( 0, 0, "", 1, 0, "C" );
      $this->Ln();
      $this->csvfile .= "\n";
      $this->csvfile .= $name . "\n";
      $this->csvfile .= "\n";
    }
    //Create footer
    //Function to total up the values;
    function createfooter( $total = false, $formats = "", $hidecolumns = "" ) {
      $columns = $this->columns;
      $left    = 0;
      $this->Ln();
      $this->Cell( 0, 0, "", 1, 0, "C" );
      $this->Ln();
      if ( $total ) {
        $this->SetY( $this->GetY() + 0.5 );
        $this->Cell( 0, 0, "", 1, 0, "C" );
        $this->Ln();
        $this->csvfile .= "\n";
      }
      for ( $i = 0; $i < count( $columns ); $i++ ) {
        if ( $columns[$i]["align"] == "left" ) {
          $align = "L";
        } else {
          $align = "R";
        }
        if ( $total ) {
          $value = $this->grandtotals[strtolower( $columns[$i]["alias"] )];
          if ( $columns[$i]["comptype"] != "" ) {
            $compute = str_replace( "columntotals", "grandtotals", strtolower( $columns[$i]["comptype"] ) );
            //echo '$value = '.$compute.";";
            eval( '$value = ' . $compute . ";" );
          }
        } else {
          $value = $this->columntotals[strtolower( $columns[$i]["alias"] )];
          if ( $columns[$i]["comptype"] != "" ) {
            $compute = strtolower( $columns[$i]["comptype"] );
            //echo '$value = '.$compute.";";
            eval( '$value = ' . $compute . ";" );
          }
        }
        if ( strtolower( $this->columns[$i]["type"] ) == "currency" ) {
          $value = number_format( $value, 2 );
        } else if ( strtolower( $this->columns[$i]["type"] ) == "integer" ) {
          $value = number_format( $value );
        }
        $left = $columns[$i]["ratiowidth"];
        if ( $this->columns[$i]["totaltype"] == "" ) {
          $value = "";
        } else {
          if ( $formats[strtolower( $this->columns[$i]["alias"] )] ) {
            $value = $formats[strtolower( $columns[$i]["alias"] )]["prefix"] . $value . $formats[strtolower( $columns[$i]["alias"] )]["suffix"];
          }
        }
        if ( strtolower( $columns[$i]["alias"] ) != strtolower( $this->groupby ) && $hidecolumns[strtolower( $columns[$i]["alias"] )] != 1 ) {
          $this->SetFont( $this->font, 'B', $this->heading );
          $this->Cell( $left, 5, $value, 0, 0, "$align" );
          $this->csvfile .= '"' . $value . '"' . $this->csvdelim;
        }
      }
      $this->csvfile = substr( $this->csvfile, 0, -1 );
      foreach ( $this->columntotals as $c => $tot ) {
        $this->grandtotals[$c] += $tot;
      }
      unset( $this->columntotals );
      $this->Ln();
      $this->csvfile .= "\n";
    }
    //Get Alias for field
    function getalias( $field ) {
      foreach ( $this->columns as $id => $value ) {
        if ( $value["name"] == $field ) {
          return $value["alias"];
          exit;
        }
      }
      return $field;
    }
    //Create the rows
    function createrows( $records, $dontshow, $formats, $hidecolumns, $csvdelim ) {
      if ( $dontshow != "" ) {
        $dontshow = str_replace( '$record', '$row', $dontshow );
      }
      foreach ( $records as $id => $row ) {
        $columns = $this->columns;
        if ( $dontshow != "" ) {
          eval( ' $notshow = (' . $dontshow . ');' );
        } else {
          $notshow = false;
        }
        if ( $notshow == false ) //if we can show these records
          {
          if ( $this->groupby ) {
            $groupby = strtoupper( $this->getalias( $this->groupby ) );
            if ( $row[$groupby] != $this->oldgroup ) {
              $this->oldgroup = $row[$groupby];
              $this->creategroupby( $this->oldgroup, $formats, $hidecolumns );
            }
          }
          $left = 0;
          $x    = 10;
          $y    = $this->GetY() + 2;
          if ( $this->orientation == "P" ) {
            if ( $y > 270 )
              $this->AddPage();
          } else {
            if ( $y > 180 )
              $this->AddPage();
          }
          $y = $this->GetY() + 2;
          for ( $i = 0; $i < count( $columns ); $i++ ) {
            if ( $columns[$i]["align"] == "left" ) {
              $align = "L";
            } else {
              $align = "R";
            }
            $left  = $columns[$i]["ratiowidth"];
            $value = $row[strtoupper( $columns[$i]["alias"] )];
            if ( $columns[$i]["type"] == "BLOB" ) {
              //check if blob is an image perhaps ???
              $value = "[BLOB]";
            }
            $totaltype = strtolower( $this->columns[$i]["totaltype"] );
            if ( $totaltype != "" ) {
              if ( !$this->columntotals[strtolower( $columns[$i]["alias"] )] ) {
                $this->columntotals[strtolower( $columns[$i]["alias"] )] = 0;
              }
              if ( $totaltype == "sum" ) {
                $this->columntotals[strtolower( $columns[$i]["alias"] )] += $value;
              } else if ( $totaltype == "avg" ) {
                if ( $this->columntotals[strtolower( $columns[$i]["alias"] )] != "" && $this->columntotals[strtolower( $columns[$i]["alias"] )] != 0 ) {
                  $this->columntotals[strtolower( $columns[$i]["alias"] )] += $value;
                  $this->columntotals[strtolower( $columns[$i]["alias"] )] = $this->columntotals[strtolower( $columns[$i]["alias"] )] / 2;
                } else {
                  $this->columntotals[strtolower( $columns[$i]["alias"] )] = $value;
                }
              } else if ( $totaltype == "count" ) {
                $this->columntotals[strtolower( $columns[$i]["alias"] )]++;
              }
            }
            //Format
            if ( strtolower( $this->columns[$i]["type"] ) == "currency" ) {
              $value = number_format( $value, 2 );
            } else if ( strtolower( $this->columns[$i]["type"] ) == "integer" ) {
              $value = number_format( $value );
            }
            //echo $columns[$i]["field"]." != ".$this->groupby;
            if ( $formats[strtolower( $columns[$i]["alias"] )] ) {
              $value = $formats[strtolower( $columns[$i]["alias"] )]["prefix"] . $value . $formats[strtolower( $columns[$i]["alias"] )]["suffix"];
            }
            //echo strtolower($columns[$i]["alias"])." - ".strtolower($this->groupby);
            if ( strtolower( $columns[$i]["alias"] ) != strtolower( $this->groupby ) && $hidecolumns[strtolower( $columns[$i]["alias"] )] != 1 ) {
              // echo  "$x, $y - $value  <br>";
              $this->SetY( $y );
              $this->SetX( $x );
              $value = strip_tags( $value );
              $this->SetFont( $this->font, '', $this->line );
              $this->MultiCell( $left, 2, $value, 0, "$align" );
              $this->csvfile .= '"' . strip_tags( $value ) . '"' . $this->csvdelim;
              $x += $left;
            }
          }
          $this->csvfile = substr( $this->csvfile, 0, -1 );
          $this->csvfile .= "\n";
        }
      }
      $this->createfooter( false, $formats, $hidecolumns );
    }
    //Create the report
    function execute( $outputpath = "output/", $reporttitle = "New Report", $companyname = "New Company", $DB, $sql, $orientation = "P", $pagesize = "A4", $groupby = "", $totalcolumns = "", $compcolumns = "", $extraheader = "", $createcsv = true, $dontshow = "", $formats = "", $hidecolumns = "", $csvdelim = ",", $computedcolumns = array( ), $debug = false ) {
      $this->font        = "Arial";
      $this->heading     = 8;
      $this->line        = 7;
      $this->groupby     = $groupby;
      $this->records     = $DB->get_row( $sql, 2, true, $computedcolumns );
      $this->columns     = $DB->fieldinfo;
      $this->reporttitle = $reporttitle;
      $this->company     = $companyname;
      $this->SetMargins( 10, 3, 4 );
      $this->orientation = $orientation;
      $this->extraheader = $extraheader;
      $this->csvfile     = "";
      $this->hidecolumns = $hidecolumns;
      $this->csvdelim    = $csvdelim;
      //Make lowercase computed columns
      foreach ( $compcolums as $id => $value ) {
        $compcolumns[strtolower( $id )] = strtolower( $value );
      }
      if ( $this->records ) {
        //Create the format functionality fieldname,prefix,suffix|fieldname,prefix,suffix|.....
        $newformat = array( );
        $formats   = explode( "|", strtolower( $formats ) );
        foreach ( $formats as $id => $value ) {
          $value                                  = explode( ",", $value );
          $newformat[trim( $value[0] )]["prefix"] = $value[1];
          $newformat[trim( $value[0] )]["suffix"] = $value[2];
        }
        $formats  = $newformat;
        $debugout = "";
        if ( $debug ) {
          $debugout .= print_r( $this->columns, 1 );
        }
        if ( $orientation == "P" ) {
          if ( $pagesize == "A3" ) {
            $pagewidth = 295;
          } else //Assume A4 to be default
            {
            $pagewidth = 196;
          }
        } else {
          if ( $pagesize == "A3" ) {
            $pagewidth = 406;
          } else //Assume A4 to be default
            {
            $pagewidth = 284;
          }
        }
        $total = 0;
        for ( $i = 0; $i < count( $this->columns ); $i++ ) {
          if ( strtolower( $this->columns[$i]["name"] ) != strtolower( $groupby ) && $hidecolumns[strtolower( $this->columns[$i]["alias"] )] != 1 ) {
            if ( $this->columns[$i]["htmllength"] == "" )
              $this->columns[$i]["htmllength"] = 100;
            $total = $total + $this->columns[$i]["htmllength"];
          }
        }
        if ( $debug ) {
          $debugout .= "<pre> $total </pre>";
        }
        for ( $i = 0; $i < count( $this->columns ); $i++ ) {
          if ( $this->columns[$i]["htmllength"] == "" )
            $this->columns[$i]["htmllength"] = 120;
          $this->columns[$i]["ratiowidth"] = ( $this->columns[$i]["htmllength"] / $total ) * $pagewidth;
          //see if there must be totals
          foreach ( $totalcolumns as $type => $value ) {
            foreach ( $value as $id => $fieldname ) {
              if ( $debug ) {
                $debugout .= strtolower( $fieldname ) . " === " . strtolower( $this->columns[$i]["alias"] ) . "<br>";
              }
              // echo "<!-- ";
              // echo strtolower($fieldname)." === ".strtolower($this->columns[$i]["alias"])."<br>";
              /// echo "-->"; 
              if ( strtolower( $fieldname ) == strtolower( $this->columns[$i]["alias"] ) ) {
                $this->columns[$i]["totaltype"] = $type;
              }
            }
          }
          //see if there must be calculations done
          foreach ( $compcolumns as $fieldname => $value ) {
            if ( $debug ) {
              $debugout .= strtolower( $fieldname ) . " === " . strtolower( $this->columns[$i]["alias"] ) . " - $value <br>";
            }
            if ( strtolower( $fieldname ) == strtolower( $this->columns[$i]["alias"] ) ) {
              $this->columns[$i]["comptype"] = $value;
            }
          }
        }
        if ( $debug ) {
          $debugout .= print_r( $this->columns, 1 );
        }
        //echo "<!--";
        //print_r ($this->columns); 
        //echo "-->";
        $this->AliasNbPages();
        //$this->createheader();
        $this->AddPage();
        if ( $debug ) {
          $debugout .= print_r( $this->records, 1 );
        }
        $this->createrows( $this->records, $dontshow, $formats, $hidecolumns, $csvdelim );
        $this->createfooter( true, $formats, $hidecolumns );
        $filename    = rand( 100000, 999999 );
        $csvfilename = $filename . ".csv";
        $filename    = $filename . ".pdf";
        $this->Output( $outputpath . $filename, "F" );
        $this->Close();
        file_put_contents( $outputpath . $csvfilename, $this->csvfile );
        $result["filename"] = $outputpath . $filename;
        $result["csvfile"]  = $outputpath . $csvfilename;
        if ( $debug ) {
          $result["debug"] = $debugout;
        }
      } else {
        $result["filename"] = "";
        $result["csvfile"]  = "";
        $result["debug"]    = "SQL Error or No Data";
      }
      return $result;
    }
  }
}
?>