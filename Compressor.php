<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2010 Leo Feyer
 *
 * Formerly known as TYPOlight Open Source CMS.
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  Andreas Schempp 2010
 * @author     Andreas Schempp <andreas@schempp.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 * @version    $Id$
 */


class Compressor extends Frontend
{
	
	/**
	 * Combine & Compress Javascript & CSS files
	 */
	public function compressFiles(Database_Result $objPage, Database_Result &$objLayout, PageRegular &$objPageRegular)
	{
		// Run Mootools template first, so we can compress potential CSS/JS files
		$strMootools = '';
		$arrMootools = deserialize($objLayout->mootools, true);

		// Add MooTools templates
		foreach ($arrMootools as $strTemplate)
		{
			if ($strTemplate == '')
			{
				continue;
			}

			$objTemplate = new FrontendTemplate($strTemplate);

			// Backwards compatibility
			try
			{
				$strMootools .= $objTemplate->parse();
			}
			catch (Exception $e)
			{
				$this->log($e->getMessage(), 'PageRegular createFooterScripts()', TL_ERROR);
			}
		}
		
		array_insert($GLOBALS['TL_MOOTOOLS'], 0, array($strMootools));
		$objLayout->mootools = '';
		
		// Compress Javascript
		if ($objLayout->compressJavascript && is_array($GLOBALS['TL_JAVASCRIPT']) && count($GLOBALS['TL_JAVASCRIPT']))
		{
			$arrAggregator = array();
			$GLOBALS['TL_JAVASCRIPT'] = array_unique($GLOBALS['TL_JAVASCRIPT']);
			
			foreach( $GLOBALS['TL_JAVASCRIPT'] as $i => $file )
			{
				list($file, $version) = explode('?', $file);
				
				if (is_file(TL_ROOT . '/' . $file))
				{
					$key = md5($file .'-'. filemtime(TL_ROOT . '/' . $file) .'-'.$version);
					$arrAggregator[$key] = $file;
					unset($GLOBALS['TL_JAVASCRIPT'][$i]);
				}
			}

			// Create the aggregated style sheet
			if (count($arrAggregator) > 0)
			{
				$strContent .= file_get_contents(TL_ROOT . '/' . $file);
				
				$key = substr(md5(implode('-', array_keys($arrAggregator))), 0, 16);

				// Load the existing file
				if (!file_exists(TL_ROOT .'/system/html/'. $key .'.js'))
				{
					$content = '';

					foreach ($arrAggregator as $file)
					{
						// Adjust the file paths
						$content .= file_get_contents(TL_ROOT .'/'. $file);
					}
					$objFile = new File('system/html/'. $key .'.js');
					$objFile->write(JSMin::minify($content));
					$objFile->close();
				}
				
				// MooTools scripts
				if ($objLayout->mooSource == 'moo_googleapis')
				{
					$mooScripts  = '<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/mootools/'. MOOTOOLS_CORE .'/mootools-yui-compressed.js"></script>' . "\n";
					$mooScripts .= '<script type="text/javascript" src="plugins/mootools/mootools-more.js?'. MOOTOOLS_MORE .'"></script>' . "\n";
				}
				else
				{
					$mooScripts  = '<script type="text/javascript" src="plugins/mootools/mootools-core.js?'. MOOTOOLS_CORE .'"></script>' . "\n";
					$mooScripts .= '<script type="text/javascript" src="plugins/mootools/mootools-more.js?'. MOOTOOLS_MORE .'"></script>' . "\n";
				}
				
				array_insert($GLOBALS['TL_HEAD'], 0, array($mooScripts, '<script type="text/javascript" src="system/html/'. $key .'.js"></script>'."\n"));
				$objPageRegular->Template->mooScripts = '';
			}
		}
		
		
		// Compress CSS
		if ($objLayout->compressStyleSheets)
		{
			$strStyleSheets = '';
			$strCcStyleSheets = '';
			$arrStyleSheets = deserialize($objLayout->stylesheet);
			$arrAggregator = array();
	
			// Internal style sheets
			if (is_array($GLOBALS['TL_CSS']) && count($GLOBALS['TL_CSS']))
			{
				foreach (array_unique($GLOBALS['TL_CSS']) as $i => $file)
				{
					list($file, $media) = explode('|', $file);
					list($file, $version) = explode('?', $file);
					
					if (is_file(TL_ROOT . '/' . $file))
					{
						$media = (($media != '') ? $media : 'all');
						$key = md5($file .'-'. filemtime(TL_ROOT . '/' . $file) .'-'. $media);
		
						$arrAggregator[''][$key] = array
						(
							'name' => $file,
							'media' => $media
						);
						
						unset($GLOBALS['TL_CSS'][$i]);
					}
				}
			}
	
			// Default TinyMCE style sheet
			if (!$objLayout->skipTinymce && file_exists(TL_ROOT . '/' . $GLOBALS['TL_CONFIG']['uploadPath'] . '/tinymce.css'))
			{
				$key = md5($GLOBALS['TL_CONFIG']['uploadPath'] . '/tinymce.css' .'-'. filemtime(TL_ROOT . '/' . $GLOBALS['TL_CONFIG']['uploadPath'] . '/tinymce.css') .'-screen');
				
				$arrAggregator[''][$key] = array
				(
					'name' => $GLOBALS['TL_CONFIG']['uploadPath'] . '/tinymce.css',
					'media' => 'screen'
				);
				
				$objLayout->skipTinymce = '1';
			}
	
			// User style sheets
			if (is_array($arrStyleSheets) && strlen($arrStyleSheets[0]))
			{
				$objStylesheets = $this->Database->execute("SELECT *, (SELECT MAX(tstamp) FROM tl_style WHERE tl_style.pid=tl_style_sheet.id) AS tstamp2 FROM tl_style_sheet WHERE id IN (" . implode(', ', $arrStyleSheets) . ") ORDER BY FIELD(id, " . implode(', ', $arrStyleSheets) . ")");
	
				while ($objStylesheets->next())
				{
					// Try to aggregate regular style sheets
					if ($objLayout->aggregate)
					{
						$key = md5($objStylesheets->id .'-'. max($objStylesheets->tstamp, $objStylesheets->tstamp2) .'-'. $objStylesheets->media);
	
						$arrAggregator[$objStylesheets->cc][$key] = array
						(
							'name' => $objStylesheets->name . '.css',
							'media' => implode(',', deserialize($objStylesheets->media))
						);
						
						unset($arrStyleSheets[array_search($objStylesheets->id, $arrStyleSheets)]);
					}
	
					// Add each style sheet separately
					else
					{
						$strStyleSheet = sprintf('<link rel="stylesheet" href="%s" type="text/css" media="%s" />',
												 $objStylesheets->name . '.css?' . max($objStylesheets->tstamp, $objStylesheets->tstamp2),
												 implode(',', deserialize($objStylesheets->media)));
	
						if ($objStylesheets->cc)
						{
							$strStyleSheet = '<!--[' . $objStylesheets->cc . ']>' . $strStyleSheet . '<![endif]-->';
						}
	
						$strCcStyleSheets .= $strStyleSheet . "\n";
					}
				}
				
				$objLayout->stylesheet = $arrStyleSheets;
			}
			
			// Create the aggregated style sheet
			if (count($arrAggregator) > 0)
			{
				// Include CSS without conditinal comment first
				ksort($arrAggregator);
				
				foreach( $arrAggregator as $cc => $arrFiles )
				{
					$key = substr(md5(implode('-', array_keys($arrFiles))), 0, 16);
	
					// Load the existing file
					if (file_exists(TL_ROOT .'/system/html/'. $key .'.css'))
					{
						if ($cc != '')
						{
							$strCcStyleSheets .= '<!--[' . $cc . ']><link rel="stylesheet" href="system/html/'. $key .'.css" type="text/css" media="all" /><![endif]-->' . "\n";
						}
						else
						{
							$strStyleSheets .= '<link rel="stylesheet" href="system/html/'. $key .'.css" type="text/css" media="all" />' . "\n";
						}
					}
	
					// Create a new file
					else
					{
						$objFile = new File('system/html/'. $key .'.css');
	
						foreach ($arrFiles as $file)
						{
							// Adjust the file paths
							$content = file_get_contents(TL_ROOT .'/'. $file['name']);
							$content = preg_replace('/url\("?([^")]+)"?\)/is', 'url("../../'. dirname($file['name']) .'/$1")', $content);
							$content = Minify_CSS_Compressor::process($content);
	
							// Append the style sheet
							$objFile->append('@media '. (($file['media'] != '') ? $file['media'] : 'all') ."{\n". $content .'}');
						}
	
						$objFile->close();
						
						if ($cc != '')
						{
							$strCcStyleSheets .= '<!--[' . $cc . ']><link rel="stylesheet" href="system/html/'. $key .'.css" type="text/css" media="all" /><![endif]-->' . "\n";
						}
						else
						{
							$strStyleSheets .= '<link rel="stylesheet" href="system/html/'. $key .'.css" type="text/css" media="all" />' . "\n";
						}
					}
				}
			}
			
			// Always add conditional style sheets at the end
			$strStyleSheets .= $strCcStyleSheets;
			
			array_insert($GLOBALS['TL_HEAD'], 0, array($strStyleSheets));
		}
		
		// Add hook to compress template
		if ($objLayout->compressTemplate)
		{
			$GLOBALS['TL_HOOKS']['outputFrontendTemplate'][] = array('Compressor', 'compressTemplate');
		}
	}
	
	
	/**
	 * Compress HTML output
	 */
	public function compressTemplate($strContent, $strTemplate)
	{
		return Minify_HTML::minify($strContent);
	}
}

