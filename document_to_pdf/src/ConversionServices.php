<?php

namespace Drupal\document_to_pdf;

use PhpOffice\PhpWord\Shared\XMLWriter;
use XMLReader;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Mpdf\Mpdf;

/**
 * GRL Common function services Implementations.
 */
class ConversionServices {

/**
 * Fetch the document data for docx file
 */
  public function fetch_docx_data($file_path, $file_name_without_ext) {
    $host = \Drupal::request()->getSchemeAndHttpHost();
    $docxFilePath = $file_path;
    // Specify the path for the output XML file.
    $xmlFilePath = 'sites/default/files/' . $file_name_without_ext . '.xml';
    // Create a new ZipArchive object.
    $zip = new \ZipArchive();

    // Open the DOCX file.
    if ($zip->open($docxFilePath) == TRUE) {
      // Locate the document.xml file in the ZIP archive.
      $xmlIndex = $zip->locateName('word/document.xml');

      // Extract the content of the document.xml file.
      $xmlContent = $zip->getFromIndex($xmlIndex);

      // Close the ZIP archive.
      $zip->close();
    }
    else {
        // Handle the case where the ZIP archive cannot be opened.
        exit('Failed to open the DOCX file.');
    }
    // Create an XMLWriter object.
      $xmlWriter = new XMLWriter();
      $xmlWriter->openMemory();
      // Extract the XML tags from the content.
      $xmlWriter->writeRaw($xmlContent);

      $xmlWriter->endDocument();

      // Get the XML content as a string.
      $xmlString = $xmlWriter->outputMemory();

      // Save the XML content to a file.
      file_put_contents($xmlFilePath, $xmlString);
      //Create a directory for saving file
      $directory = 'doc_imgs';
      // Get the default public file directory path.
      $directory_path = 'public://' . $directory;
      $file_system = \Drupal::service('file_system');
      $file_system->prepareDirectory($directory_path,FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      // Extract and Save all the images in a local folder according to the image names in the array before.
      $zip_file = new \ZipArchive();
      $zip_file_open = $zip_file->open($docxFilePath);
      $j = 0;
      for ($i = 0; $i < $zip_file->numFiles; $i++) {
        $zip_element = $zip_file->statIndex($i);
        // Check for images in the array. If exist extract the image name.
        if (preg_match("([^\s]+(\.(?i)(jpg|jpeg|png))$)", $zip_element['name'])) {
          $explode_img_media = explode('word/media/', $zip_element['name']);
          $image_name_new    = $userid . '_' . $explode_img_media[1];
          // Save the file in the local files folder.
          $a[$i] = $zip_file->getFromIndex($i);
          if (!file_exists('sites/default/files/doc_imgs/' . $image_name_new)) {
            file_put_contents('sites/default/files/doc_imgs/' . $image_name_new, $a[$i]);
            $img_name_arr[$j] = $image_name_new;
            $j++;
          }
          else {
            $time_now = time();
            $img_name_explode = explode(".", $image_name_new);
            $image_name_new = $userid . '_' . $img_name_explode[0] . '_' . $time_now . '.' . $img_name_explode[1];
            file_put_contents('sites/default/files/doc_imgs/' . $image_name_new, $a[$i]);
            $img_name_arr[$j] = $image_name_new;
            $j++;
          }
        }
      }
      // Convert the xml tags to html.
      $reader = new \XMLReader();
      $reader->open($xmlFilePath);

      // Set up variables for formatting.
      $text = '';
      $formatting['bold'] = 'closed';
      $formatting['italic'] = 'closed';
      $formatting['underline'] = 'closed';
      $formatting['header'] = 0;
      $formatting['color'] = 'closed';
      $formatting['shd'] = 'closed';
      $formatting['sty'] = 'closed';
      $formatting['sz'] = 'closed';
      // Loop through docx xml dom.
      while ($reader->read()) {
        // Look for new paragraphs.
        if ($reader->nodeType == XMLREADER::ELEMENT && $reader->name === 'w:p') {
          // Set up new instance of XMLReader for parsing paragraph independantly.
          $paragraph = new \XMLReader();
          $p = $reader->readOuterXML();
          $paragraph->xml($p);

          // Search for heading.
          preg_match('/<w:pStyle w:val="(Heading.*?[1-6])"/', $p, $matches);
          switch ($matches[1]) {
            case 'Heading1': $formatting['header'] = 1;

              break;

            case 'Heading2': $formatting['header'] = 2;

              break;

            case 'Heading3': $formatting['header'] = 3;

              break;

            case 'Heading4': $formatting['header'] = 4;

              break;

            case 'Heading5': $formatting['header'] = 5;

              break;

            case 'Heading6': $formatting['header'] = 6;

              break;

            default:  $formatting['header'] = 0;

              break;
          }

          // Open h-tag or paragraph.
          $text .= ($formatting['header'] > 0) ? '<h' . $formatting['header'] . '>' : '<p>';
          $i = 0;
          // Loop through paragraph dom.
          while ($paragraph->read()) {
            $i++;
            // Look for elements.
            if (($paragraph->nodeType == XMLREADER::ELEMENT && $paragraph->name === 'w:r')) {
              $node = trim($paragraph->readInnerXML());
              // Add <br> tags.
              if (strstr($node, '<w:br ')) {
                $text .= '<br>';
              }
              $style = 'close';
              // Look for formatting tags.
              $formatting['bold'] = (strstr($node, '<w:b ')) ? 'open' : 'closed';
              $formatting['bold_ms'] = (strstr($node, '<w:b/>')) ? 'open' : 'closed';
              $formatting['italic'] = (strstr($node, '<w:i ')) ? 'open' : 'closed';
              $formatting['italic_ms'] = (strstr($node, '<w:i/>')) ? 'open' : 'closed';
              $formatting['underline'] = (strstr($node, '<w:u ')) ? 'open' : 'closed';
              $formatting['color'] = (strstr($node, '<w:color ')) ? 'open' : 'closed';
              $formatting['shd'] = (strstr($node, '<w:shd ')) ? 'open' : 'closed';
              $formatting['shd_ms'] = (strstr($node, '<w:highlight ')) ? 'open' : 'closed';
              $formatting['sz'] = (strstr($node, '<w:sz ')) ? 'open' : 'closed';
              $formatting['img'] = (strstr($node, '<pic:cNvPr ')) ? 'open' : 'closed';

              if ($formatting['color'] == 'open') {
                $style = 'open';
                $str_val = strstr($node, '<w:color w:val="');
                $explode_color_val = explode('<w:color w:val="', $str_val);
                $explode_color_close = explode('"/>', $explode_color_val[1]);
                if (count($explode_color_close) == 1) {
                  $explode_color_close = explode('" />', $explode_color_val[1]);
                }
                $color_val[$i] .= "color: #" . $explode_color_close[0] . ";";
              }
              if ($formatting['sz'] == 'open') {
                $style = 'open';
                $sz_val = strstr($node, '<w:sz w:val="');
                $explode_sz_val = explode('<w:sz w:val="', $sz_val);
                $explode_sz_close = explode('"/>', $explode_sz_val[1]);
                $fsize = $explode_sz_close[0] / 2;
                $color_val[$i] .= "font-size: " . $fsize . "px;";
              }
              if ($formatting['shd'] == 'open') {
                $style = 'open';
                $shd_val = strstr($node, '<w:shd w:fill="');
                $explode_shd_val = explode('<w:shd w:fill="', $shd_val);
                $explode_shd_close = explode('" w:val="clear"/>', $explode_shd_val[1]);
                if ($explode_shd_close[0] != 'auto') {
                  $color_val[$i] = $color_val[$i] . "background-color:#" . $explode_shd_close[0] . ";";
                }
              }
              if ($formatting['shd_ms'] == 'open') {
                $style = 'open';
                $shd_val = strstr($node, '<w:highlight w:val="');
                $explode_shd_val = explode('<w:highlight w:val="', $shd_val);
                $explode_shd_val_close = explode('"/>', $explode_shd_val[1]);
                $hex_code = \Drupal::service('document_to_pdf.conversionservices')->return_hex_code($explode_shd_val_close[0]);
                $color_val[$i] = $color_val[$i] . "background-color:" . $hex_code . ";";
              }

              $formatting['sty'] = ($style == 'open' ? 'open' : 'close');
              // Build text string of doc.
              $text .= (($formatting['bold'] == 'open') ? '<b>' : '') .
                                      (($formatting['bold_ms'] == 'open') ? '<b>' : '') .
                                     (($formatting['italic'] == 'open') ? '<em>' : '') .
                                     (($formatting['italic_ms'] == 'open') ? '<em>' : '') .
                                     (($formatting['underline'] == 'open') ? '<u>' : '') .
                                     (($formatting['sty'] == 'open') ? '<span style="' . $color_val[$i] . '">' : '') .
                                     htmlentities(iconv('UTF-8', 'ASCII//TRANSLIT', $paragraph->expand()->textContent)) .
                                     '</span>' .
                                     (($formatting['underline'] == 'closed') ? '</u>' : '') .
                                     (($formatting['italic'] == 'closed') ? '</em>' : '') .
                                     (($formatting['italic_ms'] == 'closed') ? '</em>' : '') .
                                     (($formatting['bold_ms'] == 'closed') ? '</b>' : '') .
                                     (($formatting['bold'] == 'closed') ? '</b>' : '');

              if ($formatting['img'] == 'open' && !empty($img_name_arr)) {
                $img_name = $img_name_arr[0];
                array_shift($img_name_arr);
                $text = $text . '<br/><img src="sites/default/files/doc_imgs/' . $img_name . '" /><br/>';
              }
              $color_val[$i] = '';
              // Reset formatting variables.
              foreach ($formatting as $key => $format) {
                if ($format == 'open') {
                  $formatting[$key] = 'opened';
                }
                if ($format == 'close') {
                  $formatting[$key] = 'closed';
                }
              }
            }
          }
          $text .= ($formatting['header'] > 0) ? '</h' . $formatting['header'] . '>' : '</p>';
        }

      }
      $reader->close();

      // Suppress warnings. loadHTML does not require valid HTML but still warns against it...
      // fix invalid html.
      $doc = new \DOMDocument();
      $doc->encoding = 'UTF-8';
      @$doc->loadHTML($text);
      \Drupal::logger('file_name_without_ext')->notice('<pre>' . print_r($file_name_without_ext, 1) . '</pre>');
      $goodHTML = simplexml_import_dom($doc)->asXML();
      $default_files_directory = \Drupal::config('system.file')->get('default_scheme') . '://';
      // // Delete xml and docx file from local;.
      unlink($default_files_directory . '/' . $file_name_without_ext . '.xml');
      unlink($default_files_directory . '/' .$file_name_without_ext . '.docx');
      //$goodHTML = 1;
      return $goodHTML;
  }

/**
 * Fetch the document data for pptx file
 */
  public function fetch_pptx_data() {

  }

/**
 * Fetch the document data for xlx file
 */
  public function fetch_xlx_data() {

  }

  /**
   * Returns the hexcode of the color.
   */
  public function return_hex_code($color) {
    $colors = [
      'aliceblue' => 'F0F8FF',
      'antiquewhite' => 'FAEBD7',
      'aqua' => '00FFFF',
      'aquamarine' => '7FFFD4',
      'azure' => 'F0FFFF',
      'beige' => 'F5F5DC',
      'bisque' => 'FFE4C4',
      'black' => '000000',
      'blanchedalmond ' => 'FFEBCD',
      'blue' => '0000FF',
      'blueviolet' => '8A2BE2',
      'brown' => 'A52A2A',
      'burlywood' => 'DEB887',
      'cadetblue' => '5F9EA0',
      'chartreuse' => '7FFF00',
      'chocolate' => 'D2691E',
      'coral' => 'FF7F50',
      'cornflowerblue' => '6495ED',
      'cornsilk' => 'FFF8DC',
      'crimson' => 'DC143C',
      'cyan' => '00FFFF',
      'darkblue' => '00008B',
      'darkcyan' => '008B8B',
      'darkgoldenrod' => 'B8860B',
      'darkgray' => 'A9A9A9',
      'darkgreen' => '006400',
      'darkgrey' => 'A9A9A9',
      'darkkhaki' => 'BDB76B',
      'darkmagenta' => '8B008B',
      'darkolivegreen' => '556B2F',
      'darkorange' => 'FF8C00',
      'darkorchid' => '9932CC',
      'darkred' => '8B0000',
      'darksalmon' => 'E9967A',
      'darkseagreen' => '8FBC8F',
      'darkslateblue' => '483D8B',
      'darkslategray' => '2F4F4F',
      'darkslategrey' => '2F4F4F',
      'darkturquoise' => '00CED1',
      'darkviolet' => '9400D3',
      'deeppink' => 'FF1493',
      'deepskyblue' => '00BFFF',
      'dimgray' => '696969',
      'dimgrey' => '696969',
      'dodgerblue' => '1E90FF',
      'firebrick' => 'B22222',
      'floralwhite' => 'FFFAF0',
      'forestgreen' => '228B22',
      'fuchsia' => 'FF00FF',
      'gainsboro' => 'DCDCDC',
      'ghostwhite' => 'F8F8FF',
      'gold' => 'FFD700',
      'goldenrod' => 'DAA520',
      'gray' => '808080',
      'green' => '008000',
      'greenyellow' => 'ADFF2F',
      'grey' => '808080',
      'honeydew' => 'F0FFF0',
      'hotpink' => 'FF69B4',
      'indianred' => 'CD5C5C',
      'indigo' => '4B0082',
      'ivory' => 'FFFFF0',
      'khaki' => 'F0E68C',
      'lavender' => 'E6E6FA',
      'lavenderblush' => 'FFF0F5',
      'lawngreen' => '7CFC00',
      'lemonchiffon' => 'FFFACD',
      'lightblue' => 'ADD8E6',
      'lightcoral' => 'F08080',
      'lightcyan' => 'E0FFFF',
      'lightgoldenrodyellow' => 'FAFAD2',
      'lightgray' => 'D3D3D3',
      'lightgreen' => '90EE90',
      'lightgrey' => 'D3D3D3',
      'lightpink' => 'FFB6C1',
      'lightsalmon' => 'FFA07A',
      'lightseagreen' => '20B2AA',
      'lightskyblue' => '87CEFA',
      'lightslategray' => '778899',
      'lightslategrey' => '778899',
      'lightsteelblue' => 'B0C4DE',
      'lightyellow' => 'FFFFE0',
      'lime' => '00FF00',
      'limegreen' => '32CD32',
      'linen' => 'FAF0E6',
      'magenta' => 'FF00FF',
      'maroon' => '800000',
      'mediumaquamarine' => '66CDAA',
      'mediumblue' => '0000CD',
      'mediumorchid' => 'BA55D3',
      'mediumpurple' => '9370D0',
      'mediumseagreen' => '3CB371',
      'mediumslateblue' => '7B68EE',
      'mediumspringgreen' => '00FA9A',
      'mediumturquoise' => '48D1CC',
      'mediumvioletred' => 'C71585',
      'midnightblue' => '191970',
      'mintcream' => 'F5FFFA',
      'mistyrose' => 'FFE4E1',
      'moccasin' => 'FFE4B5',
      'navajowhite' => 'FFDEAD',
      'navy' => '000080',
      'oldlace' => 'FDF5E6',
      'olive' => '808000',
      'olivedrab' => '6B8E23',
      'orange' => 'FFA500',
      'orangered' => 'FF4500',
      'orchid' => 'DA70D6',
      'palegoldenrod' => 'EEE8AA',
      'palegreen' => '98FB98',
      'paleturquoise' => 'AFEEEE',
      'palevioletred' => 'DB7093',
      'papayawhip' => 'FFEFD5',
      'peachpuff' => 'FFDAB9',
      'peru' => 'CD853F',
      'pink' => 'FFC0CB',
      'plum' => 'DDA0DD',
      'powderblue' => 'B0E0E6',
      'purple' => '800080',
      'red' => 'FF0000',
      'rosybrown' => 'BC8F8F',
      'royalblue' => '4169E1',
      'saddlebrown' => '8B4513',
      'salmon' => 'FA8072',
      'sandybrown' => 'F4A460',
      'seagreen' => '2E8B57',
      'seashell' => 'FFF5EE',
      'sienna' => 'A0522D',
      'silver' => 'C0C0C0',
      'skyblue' => '87CEEB',
      'slateblue' => '6A5ACD',
      'slategray' => '708090',
      'slategrey' => '708090',
      'snow' => 'FFFAFA',
      'springgreen' => '00FF7F',
      'steelblue' => '4682B4',
      'tan' => 'D2B48C',
      'teal' => '008080',
      'thistle' => 'D8BFD8',
      'tomato' => 'FF6347',
      'turquoise' => '40E0D0',
      'violet' => 'EE82EE',
      'wheat' => 'F5DEB3',
      'white' => 'FFFFFF',
      'whitesmoke' => 'F5F5F5',
      'yellow' => 'FFFF00',
      'yellowgreen' => '9ACD32',
    ];
    if (isset($colors[$color])) {
      return ('#' . $colors[$color]);
    }
    else {
      return ($color);
    }
  }

  /**
   * Convert to PDF
   */
  public function convert_to_pdf($data) {
    // Create new PDF document.
    $mpdf = new Mpdf();
    $mpdf->WriteHTML($data);
    // Output the PDF as a download
    $mpdf->Output(time().'_converted.pdf', 'I');
    $files = glob('sites/default/files/doc_imgs/*');
    // Iterate image files and delete it.
    foreach ($files as $file1) {
      if (is_file($file1)) {
        // Delete local saved image files.
        unlink($file1);
      }
    }
  }
}
