<?php
/**
* Place Article Joomla! Plugin
*
* @author    Ivan Komlev
* @copyright Copyright (C) 2012-2018 Ivan Komlev. All rights reserved.
* @license	 GNU/GPL
*/

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

use Joomla\CMS\Version;

class plgSystemPlaceArticle extends JPlugin
{
	protected $app;

	public function onAfterRender()
	{
		$version = new Version;

		$isSite=true;
		if((int)$version->getShortVersion()>=4)
			$isSite=$this->app->isClient('site');
		else
			$isSite=JFactory::getApplication()->isSite();
		
		if($isSite)
		{
			
			//Only run from the client-side, never the admin side
			if((int)$version->getShortVersion()>=4)
				$output= $this->app->getBody();
			else
				$output = JResponse::getBody();
				

			if(strpos($output,'{article')!==false)
			{
				//continue until all possible artcles will be placed in to output;
				$last_length=strlen($output);
				$count=$this->plgPlaceArticle($output);
				$length=strlen($output);
				
				if((int)$version->getShortVersion()>=4)
					$this->app->setBody($output);
				else
					JResponse::setBody($output);

			}
		}
		
		
	}
	
	
	function plgPlaceArticle(&$text_original)
	{
		$jinput=JFactory::getApplication()->input;
		$getscripts = $jinput->getInt('getscripts',0); //organize scripts generated by content plugins

		$result='';
		
		$options=array();
		
		$text=$this->strip_html_tags_textarea($text_original);
		
		$fList=$this->getListToReplace('article',$options,$text,'{}');
		$count=0;
		
		$i=0;
		foreach($fList as $f)
		{
			if($options[$i]!='')
			{
				$pair=explode(',',$options[$i]);
				
				$articleid=$pair[0];
				
				if(isset($pair[1]) and $pair[1]!='' and $pair[1]!='introtext')
				{
					//$result="\n<!-- Place Article *article.".$pair[1]."=".$articleid."* -->\n"
					$result=$this->getArticle($articleid,$pair[1]);//."\n<!-- end of Place Article -->\n";
				}
				else
				{
					//$result="\n<!-- Place Article *article=".$articleid."* -->\n"
					$result=$this->getArticle($articleid,'introtext');//."\n<!-- end of Place Article -->\n";
				
					if($this->params->get( 'allowcontentplugins' ))
					{
						$o = new stdClass();
						$o->text=$result;
						$dispatcher	= JDispatcher::getInstance();
						JPluginHelper::importPlugin('content');
						$r = $dispatcher->trigger('onContentPrepare', array ('com_content.article', &$o, &$params_, 0));
						
						if($getscripts==1)
							$this->getPosibleScriptFromJDispatcher($dispatcher); //organize scripts generated by content plugins
						
					
						$result=$o->text;
					}
				}
							
				$text_original=str_replace($fList[$i],$result,$text_original);	
				$count++;
			}
			$i++;
		}
	
		return $count;
	}
	
	
	


	
	function getArticle($articleid,$field)
	{
		$possiblefields=array('title','alias','introtext','fulltext','state','catid','created','created_by_alias','modified','images','urls','metakey','metadesc','hits','featured','language');

		if(!in_array($field,$possiblefields))
			return '<p style="background-color:red;color:white;">Place Artice: unknown artical field.</p>';
	
		// get database handle
		$db = JFactory::getDBO();
		
		$where=array();
		
		$id=(int)$articleid;
		if($id==0)
			$where[]='#__content.alias="'.$this->html2alias($articleid).'"'; //2016-11-08 - finds article by alias
		else
			$where[]='#__content.id='.$id;
		
		
			$langObj=JFactory::getLanguage();
			$nowLang=$langObj->getTag();
	
			// Filter by start and end dates.
			$nullDate = $db->Quote($db->getNullDate());
			$date = JFactory::getDate();
			$nowDate = $db->Quote($date->toSql());
			
			$where[]='(#__content.language="*" OR #__content.language="'.$nowLang.'")';
			$where[]='#__content.state=1';	
			$where[]='(#__content.publish_up = ' . $nullDate . ' OR #__content.publish_up <= ' . $nowDate . ')';
			$where[]='(#__content.publish_down = ' . $nullDate . ' OR #__content.publish_down >= ' . $nowDate . ')';
		
		
		$where_str=implode(' AND ' , $where);
				
		$query='SELECT '.$field.' FROM #__content WHERE '.$where_str.' LIMIT 1';
		
		try
		{
			$rows = $db->setQuery($query)->loadAssocList();
		}
		catch (ExecutionFailureException $e)
		{
			return null;
		}


		
		if (!$rows)
		{
			return null;
		}
		
				
		if(count($rows)!=1)
			return null;
					
		$row=$rows[0];
		
		return $row[$field];
	}
	
	function html2alias($document)
	{ 
				$search = array(
						'@<script[^>]*?>.*?</script>@si',  		// Strip out javascript 
						'@<[\/\!]*?[^<>]*?>@si',            	// Strip out HTML tags 
						'@<style[^>]*?>.*?</style>@siU', 		// Strip style tags properly 
						'@<![\s\S]*?--[ \t\n\r]*>@'         	// Strip multi-line comments including CDATA 
				);
			
				$text = preg_replace($search, '', $document);
				
				$text=str_replace('"','',$text);
				$text=str_replace("'",'',$text);
				
				return $text;
	}
	
	
	function getPosibleScriptFromJDispatcher($dispatcher2)
	{
		$script=array();
						$d=(array)$dispatcher2;
						
						if(isset($d["\0*\0_observers"]))
						{
							$array1=$d["\0*\0_observers"];
							if(is_array($array1))
							{
								
								foreach($array1 as $e1)
								{
									//print_r($e1);
									//echo '-----------------------------------------------------------';
									if(isset($e1['handler']))
									{
										$array2=$e1['handler'];
										if(is_array($array2))
										{
											foreach($e1['handler'] as $e2)
											{
												$e3=(array)$e2;
												if(isset($e3["\0*\0document"]))
												{
													$e4=$e3["\0*\0document"];
										
													$script[]=$e4->_script['text/javascript'];
												}
									
											}
											
										}
									}
									break;
								}
							}
						}
						$result=implode('',$script);
						
						//echo $result;
						$document = JFactory::getDocument();	
						$document->addCustomTag($result);
						//$document->addScriptDeclaration($result);
						
		return '';
	}
	
	function getListToReplace($par,&$options,&$text,$qtype)
	{
		$fList=array();
		$l=strlen($par)+2;
	
		$offset=0;
		do{
			if($offset>=strlen($text))
				break;
		
			$ps=strpos($text, $qtype[0].$par.'=', $offset);
			if($ps===false)
				break;
		
		
			if($ps+$l>=strlen($text))
				break;
		
		$pe=strpos($text, $qtype[1], $ps+$l);
				
		if($pe===false)
			break;
		
		$notestr=substr($text,$ps,$pe-$ps+1);

			$options[]=trim(substr($text,$ps+$l,$pe-$ps-$l));
			$fList[]=$notestr;
			

		$offset=$ps+$l;
		
			
		}while(!($pe===false));
		
		//for these with no parameters
		$ps=strpos($text, $qtype[0].$par.$qtype[1]);
		if(!($ps===false))
		{
			$options[]='';
			$fList[]=$qtype[0].$par.$qtype[1];
		}
		
		return $fList;
	}
	
	
	function strip_html_tags_textarea( $text )
	{
	    $text = preg_replace(
		array(
		// Remove invisible content
		'@<textarea[^>]*?>.*?</textarea>@siu',
		),
		array(
		' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ',"$0", "$0", "$0", "$0", "$0", "$0","$0", "$0",), $text );
     
		return $text ;
	}

}
