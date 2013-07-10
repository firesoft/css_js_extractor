<?php

class css_js_extractor
{
	const css_files_pattern='/(<!--\[if[^\]]*\]>\s*<link[^>]*href=[^>]*\.css[^>]*>\s*<!\[endif\]-->)|(<link[^>]*href=[^>]*\.css[^>]*>)/isU';
	const inline_styles_pattern='/(<!--\[if[^\]]*\]>\s*<style[^>]*>.*<\/style>\s*<!\[endif\]-->)|(<style[^>]*>.*<\/style>)/isU';
	const script_pattern='/<script[^>]*>.*<\/script>/isU';
	
	private $html;
	
	private $css_files;
	private $inline_styles;
	private $scripts;
	
	private $unique_css;//remove duplicated css files && styles;
	private $concatenate_styles;
	private $concatenate_scripts;
	
	function __construct($params=array())
	{
		$this->unique_css=true;
		$this->concatenate_styles=true;
		$this->concatenate_scripts=true;
		
		if(isset($params['unique_css']))
			$this->unique_css=$params['unique_css'];
			
		if(isset($params['concatenate_scripts']))
			$this->concatenate_scripts=$params['concatenate_scripts'];
			
		if(!isset($params['html']))
			$params['html']='';
		$this->set_html($params['html']);
	}
	
	public function init()
	{
		$this->css_files=array();
		$this->inline_styles=array();
		$this->scripts=array();
	}
	
	
	public function set_html($html='')
	{
		$this->html=$html;
		
		$this->init();
		
		if($html)
		{
			$this->extract_styles();
			$this->remove_duplicated_styles();
			
			$this->extract_scripts();
			$this->concatenate_scripts();
			
			$this->remove_duplicated_end_lines();
		}
	}
	
	private function extract_styles()
	{
		$this->extract_css_files();
		$this->extract_inline_styles();
	}
	
	private function extract_scripts()
	{
		$scripts=array();
		
		$this->html=preg_replace_callback(self::script_pattern,function($matches) use (&$scripts)
		{
			$scripts[]=$matches[0];
			return '';
		},$this->html);
		
		$this->scripts=$scripts;
	}
	
	private function extract_css_files()
	{
		$css_files_matches=array();
		
		$this->html=preg_replace_callback(self::css_files_pattern,function($matches) use (&$css_files_matches)
		{
			$css_files_matches[]=$matches[0];
			return '';
		},$this->html);
		
		$this->css_files=$css_files_matches;
	}
	
	private function extract_inline_styles()
	{
		$styles_matches=array();
		
		$this->html=preg_replace_callback(self::inline_styles_pattern,function($matches) use (&$styles_matches)
		{
			$styles_matches[]=$matches[0];
			return '';
		},$this->html);
		
		$this->inline_styles=$styles_matches;
	}
	
	public function get_html()
	{
		return $this->html;
	}
	
	public function get_styles()
	{
		return array_merge($this->css_files,$this->inline_styles);
	}
	
	public function get_scripts()
	{
		return $this->scripts;
	}
	
	private function remove_duplicated_styles()
	{
		if($this->unique_css)
		{
			$this->remove_duplicated_css_files();
			$this->remove_duplicated_inline_styles();
		}
	}
	
	private function remove_duplicated_css_files()
	{
		if($this->css_files)
		{
			//multiple array_reverse to preserve last unique value
			$this->css_files=array_reverse(array_unique(array_reverse($this->css_files)));
		}
	}
	
	private function remove_duplicated_inline_styles()
	{
		if($this->inline_styles)
		{
			//multiple array_reverse to preserve last unique value
			$this->inline_styles=array_reverse(array_unique(array_reverse($this->inline_styles)));
		}
	}
	
	private function concatenate_scripts()
	{
		if($this->scripts && $this->concatenate_scripts)
		{
			$last=null;
			$script_html='';
			$scripts=array();
			
			foreach($this->scripts as $script)
			{
				$open_length=strpos($script,'>')+1;
				$end_len=strlen('</script>');
				
				$open_part=substr($script,0,$open_length);
				$script_part=trim(substr($script,$open_length,-$end_len));
				
				if(strpos($open_part,' src=')!==false)//hybrid also as js file
				{
					if($last=='inline')
					{
						$scripts[]=$script_html."</script>\r\n";
						$script_html='';
					}
					
					$scripts[]=$script;
						
					$last='ext';
				}
				else
				{
					if($last!=='inline')
						$script_html.="<script>\r\n";
					$script_html.=$script_part."\r\n";
					$last='inline';
				}
			}
			if($last==='inline')
			{
				$scripts[]=$script_html."</script>\r\n";
			}
			$this->scripts=$scripts;
		}
	}
	
	private function remove_duplicated_end_lines()
	{
		if($this->html)
		{
			//$this->html=preg_replace("/(\t)+/",'',$this->html);
			$this->html=preg_replace("/(\r\n)+/","\n",$this->html);
			$this->html=preg_replace("/(\n)+/","\n",$this->html);
		}
	}
	
	public function inject()
	{
		$this->inject_styles();
		$this->inject_scripts();
	}
	
	private function inject_styles()
	{
		$this->html=str_replace('</head>',implode("\r\n",$this->get_styles())."\r\n</head>",$this->html);
	}
	
	private function inject_scripts()
	{
		$this->html=str_replace('</body>',implode("\r\n",$this->get_scripts())."\r\n</body>",$this->html);
	}
}