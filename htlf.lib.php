<?php
defined('SAFE')or die();
/**
A simple but useful text to code translation tool created by Leo Anthony
Awesomely called HTLF(Human Text to Language Format)
This project is still under review and not an opensource.
However,if you find the need to use this work on your 
project,please contact the author 
ifayce@gmail.com. Proudly Nigerian!
*/
	class HTLF {
		
		private static $runtime; 
		
		protected static $configs = array();
		protected static $config_namespace;
		protected function assignFlags($flag,$callback){}
		
		protected function assignMethods(){
			
		}
		
		public function parse($code){
			if(empty($code))return NULL;
				//Get the current config;
				$cfg = new htlf_abstracts_config;
				$cfg->setFromArray(self::$configs[self::$config_namespace]);
				//The runtime variable
			
			self::$runtime = array();
			if($cfg->runtimeCache === true){
					$cacheKey = __CLASS__.DELIMITER_DIR.CLIENT_REQUEST_PATH.md5(Config::get()->Secretkey).DELIMITER_DIR.(isset(self::$config_namespace)? self::$config_namespace : 'default');
					$cache = new sys_autocache;
					$cache->setStorage('sqlite');
					$cache->setnamespace($cacheKey);
					$driver = $cache->open();
					$cached = $driver->get();
					
						if(!empty($cached) AND is_array($cached)){
						self::$runtime = $cached;
						}
				}
			//Split the loaders
			self::$runtime['lines'] = explode("\n",$code);
			self::$runtime['linesTotal'] = count(self::$runtime['lines']);
			self::$runtime['defineMethods'] = strtolower(implode($cfg->methods,"|"));
			$regex = '#(['.self::$runtime['defineMethods'].']+)\?#i';
			
				for($i=0;self::$runtime['linesTotal'] > $i;$i++){
					
							$flagPassed = self::evaluateFlag($i,$cfg);
							
						if(!$flagPassed)continue;
						
					
					//Begin parseing the methods
					
						if(preg_match_all($regex, self::$runtime['lines'][$i], $matches, PREG_PATTERN_ORDER)){
							
							foreach($matches[1] as $f){
									$f = trim($f);
										if(!in_array($f,$cfg->methods)){
								throw new Exception('Function {'.$f.'} not declared');
										}
								$func_args = ltrim(substr(ltrim(self::$runtime['lines'][$i]),strlen($f)+1));
								//Get parameters up to the next function call
								$params = substr($func_args,0,strcspn($func_args,'?'));
								//Get next function call;
								$nextCall = trim(substr($params,strripos($params,' ')));
								
								
									if($nextCall !=false AND in_array($nextCall,$matches[1])){
									$arguments = trim(substr($params,0,-(strlen($nextCall))));
									}else{
										$arguments = $params;
									}
									
									
									preg_match('#([\'\"]+)(.*?)(\1)#ix',$arguments,$o);
									
										if(!empty($o) AND !is_null($o[2])){
											$arg = $o[2];
											$b = $func_args;
											$func_args = trim(substr($func_args,strlen($o[0])));
											
										}else{
											
								
								$arg = trim($arguments);
								
								$func_args = trim(substr($func_args,strlen($arg)));
								
										}
							self::$runtime['lines'][$i] = $func_args;
								
								if(strpos($arg,',') !==false){
									//Multiple parameters
									$arg = explode(',',$arg);
								}	
								if(is_string($arg) AND strpos($arg,'#') ===0){
									
									if(preg_match('/\#+([A-Z0-9_]+)/i',$arg,$vars)){
										if(array_key_exists('#'.$vars[1],self::$runtime['vars'])){
											$arg = self::$runtime['vars']['#'.$vars[1]];
										}
									}
								}elseif(is_array($arg)){
									$arg = str_replace(array_keys(self::$runtime['vars']),array_values(self::$runtime['vars']),$arg);
									
								}
								
								$r = self::callFunction(trim($f),$arg,$cfg);
								self::$runtime['vars'][] = $r;
							}
						}
						
				}
						if($cfg->runtimeCache === true AND isset($driver)){
					$driver->set(self::$runtime);
						}
				
		}
		
		private function callFunction($function,$arguments,&$cfg){
			if(empty($function)){
				throw new Exception('Function name must be a valid string');
			}
			preg_match('#([a-z-0-9]){1}([\w\s]+(\.?))#i',$function,$match);
			if(is_numeric($match[1]) || !empty($match[3])){
					throw new Exception("Function name must begin with a letter,not contain Dot");
				}
					if(array_key_exists($function,$cfg->function_override)){
						$function = $cfg->function_override[$function];
							$methods = $cfg->methods;
								$methods[$function] = $function;
						$cfg->methods = $methods;
					}
					
			switch($function){
									case 'require':
									$root = SITE.DELIMITER_DIR;
										if(file_exists($root.$arguments)){
										require_once($root.$arguments);
										}else{
											throw new Exception('File {'.file::inst($root.$arguments)->path.'} does not exist');
										}
									break;
									case 'var_get':
											if(strpos($arguments,'#') !==0){
												$arguments = '#'.$arguments;
											}
										if(array_key_exists($arguments,self::$runtime['vars'])){
											return self::$runtime['vars'][$arguments];
										}else{
											return NULL;
										}
									break;
									case 'var_export':
									
									$v = end(self::$runtime['vars']);
									
											if(empty($arguments)){
												throw new Exception('Variable name must be a valid string');
											}
										if(!is_null($v)){
											if(strpos($arguments,'#') !==0){
												$arguments = '#'.$arguments;
											}
											array_pop(self::$runtime['vars']);
											self::$runtime['vars'][$arguments] = $v;
											
										}
									break;
									case 'redirect':
									Director::profile('validation');
									$validation = new validation;
									$sgl = parse_url($arguments);
									if($validation::startsWith('http')->validate($arguments) !== true AND $validation::startsWith('https')->validate($arguments) !==true ){
									$rule = $validation::alwaysInvalid();
									$value = $arguments;
									}else{
					if(strrpos($sgl['host'],'www.') !==false){
						$sgl['host'] = str_replace('www.','',$sgl['host']);
						}
				$rule = $validation::string()->length(3,null)->noWhiteSpace()->domain();
					$value = $sgl['host'];
									}
			if($rule->validate($value)){
				Director::force_redirect($arguments);
			}
									break;
									case 'decrypt':
									case 'encrypt':
									Director::profile('crypt');
									$crpt = new crypt;
									$crpt->set_key(Config::get()->licence_key);
										if($function =='encrypt'){
											$data = $crpt->encryptOnly($arguments);
										}elseif($function=='decrypt'){
											$data = $crpt->decode($arguments);
										}
										if(isset($data)){
											return $data;
										}
										return null;
									break;
									case 'echo':
									case 'print':
									self::$runtime['vars'][] = $arguments;
									
									echo $arguments;
									break;
									case 'exit':
									exit();
									break;
									default:
										
											if(array_key_exists($function,$cfg->methods) AND (is_callable($cfg->methods[$function]) || function_exists($cfg->methods[$function]))){
													if(is_null($arguments)){
														$arguments  = array();
													}
												if(is_scalar($arguments)){
													$arguments = array($arguments);
												}
											return call_user_func_array($cfg->methods[$function],$arguments);
											}else{
												throw new Exception('Function {'.$function.'} not found');
											}
									break;
								}
		}
		
		private function evaluateFlag($i,&$cfg){
			$hasFlag= false;
					
					if(strpos(self::$runtime['lines'][$i],'-') ===0){
						//Look for flags
							$flagPassed = false;
							$hasFlag = true;
						$flagIndexes = findpos(self::$runtime['lines'][$i],'-');
						
							if(empty($flagIndexes))throw new InvalidArgumentExcpetion('Parse Error! Cannot accomplish flag buildup');
							
						foreach($flagIndexes as $index){
								$index = strpos(self::$runtime['lines'][$i],'-');
							$offset = substr(self::$runtime['lines'][$i],$index);
							$end = strcspn($offset,' ');
							
							//Now get the flag declaration
							$flag = trim(substr($offset,1,$end));
							//Remove the flag from the runtime
							
							self::$runtime['lines'][$i] = str_ireplace('-'.$flag,'',self::$runtime['lines'][$i]);
							//Get the flag parameters
							$fp = explode('=',$flag);
							$argument = $fp[1];
								//Get the argument type provided
								
								if($argument ==NULL){
									$argType = 'singleTon';
								
									//An argument that captures the default 1/0 value
								}elseif(is_numeric($argument)){
									$argType = 'indexTon';
									
									//An argument that gets the index value of a flashSheet
								}elseif(($exp = trim($argument,'[]')) !==$argument){
									$argType = 'arrayTon';
									//An argument that specifies list of options to check against
								}elseif(($exp = trim($argument,'{}')) !==$argument){
									$argType = 'expressionTon';
									//An argument that gets its value from performing a maths expressions
								}else{
									$argType = 'valueTon';
									//An argument that specifies its value
								}
								
								//Get the flagsheet model
									if(empty($cfg->flags))throw new Exception('No flag prototype available for flag{'.$fp[0].'}');
									$flagModel = $cfg->flags[$fp[0]];
								//Get the flag values and match against
									switch($argType){
										case 'singleTon':
										$fv = (int)(!empty($flagModel['i']));
										break;
										case 'arrayTon':
										$fv = (int)(in_array($flagModel['i'],explode(',',$exp)));
										
										break;
										case 'expressionTon':
										eval( "\$solution=".($exp).";");
										
										$fv = (int)($solution ==$flagModel['i']);
										break;
										case 'indexTon':
										
										$fv = (int)($flagModel['i'] ==$argument);
										break;
										case 'valueTon':
										$fv = (int)($flagModel['value'] ==$argument);
										break;
									}
									
								if($fv ===1){
									$flagPassed = true;
								}
						}//End of flag parsing loop
						
					}//End of flag processing
					if(!$hasFlag)return true;
						return $flagPassed;
		}
		public static function autoload(){
			 if (($funcs = spl_autoload_functions()) === false) {
		 
            spl_autoload_register(array('htlf', 'loadClass'),true,true);
        }else{
		spl_autoload_register(array('htlf', 'loadClass'));
		
		 foreach ($funcs as $func) {
                    spl_autoload_register($func);
                }
		}
			
		}
		public static function loadClass($class){
					if(stripos($class,'htlf') !==0)return false;
				$main = dirname(__DIR__);
			$cl = explode(DELIMITER_UNDER,$class);
				$realFile = implode(DELIMITER_DIR,$cl);
					
				if(file_exists($main.DIRECTORY_SEPARATOR.$realFile.'.php')){
					require($main.DIRECTORY_SEPARATOR.$realFile.'.php');
				}
		}
		public static function setConfig($namespace,array $config = array()){
				if(empty($namespace) || is_numeric($namespace))return false;
						self::$configs[$namespace] = $config;
						self::useConfig($namespace);
						return true;
		}
		
		public function useConfig($namespace){
			if(array_key_exists($namespace,self::$configs)){
				self::$config_namespace = $namespace;
			}
		}
		
	}

?>