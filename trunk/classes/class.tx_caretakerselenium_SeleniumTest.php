<?php

require_once 'class.tx_caretakerselenium_Selenium.php';
require_once 'class.tx_caretakerselenium_SeleniumCommand.php';

/**
tx_caretakerselenium_SeleniumTest manages the commands and executes them through a tx_caretakerseleneium_Selenium

Added IE Fix: In Internet Explorer waitForLocation includes waitForPageToLoad, so this must be caught to avoid a
timeout.

Added custom timer: You can start the test timer by adding '@startTimer:' into your selenese commands. Every call
resets the start time. You can stop the timer by adding '@stopTimer:' into your selenese commands. Only the first
call is interpreted. So take care you place your timer commands.
*/

class tx_caretakerselenium_SeleniumTest {

	private $sel;
	private $commands = array();
	private $testSuccessful = true;
	private $commandsText = '';
	private $commandsLoaded = false;
	private $host;
	
	function __construct($commands,$browser,$baseUrl,$host) {
		
		//echo $commands;
		
		$this->commandsText = $commands;
		$this->browser = $browser;
		$this->baseURL = $baseUrl;
		$this->host    = $host;
	}

	function run(){
		
		$this->setUp();
		$res = $this->testMyTestCase();
		$this->tearDown();
		return $res;
	}
	
	function setUp()  {
		
  		$this->sel = new tx_caretakerselenium_Selenium($this->browser,$this->baseURL, $this->host);
  		if($this->loadCommands()) {
  		
  			$this->commandsLoaded = true;  		
  			$this->sel->start();
  		}
  		
  	}
	
	function tearDown() {
	
		$this->sel->stop();
	
	}
	
	/*function startTests() {
		if($this->commandsLoaded) {
		
			$this->testMyTestCase();
		}
	}*/

	
	/**
	 * added advanced timer functionality
	 * 
	 * '@startTimer' starts a timer
	 * '@stopTimer' stop the timer
	 * 
	 * you can call this several times and the time between '@startTimer' and '@stopTimer' is measured
	 * and added to the total time
	 * 
	 * there is now automatic timer anymore, must be configured in the commands
	 * if no timer commands are used the time is 0, should be everytime green
	 * 
	 */
	function testMyTestCase() {
		
		if($this->commandsLoaded) {
			
			// $avoidWaitForPageToLoad = false; // needed for ie fix, UPDATE: not needed at the moment
			$time = 0; // the measured time
			$starttime = microtime(true); // time is started automatically
			$lastRound = $starttime;
			$timerRunning = true; // indicates if the timer is running
			$timeLog = array();
			
			// no automatic timer anymore
			
			// start the timer just before the first commands are send
			//$starttime = microtime(true);
			//$timerRunning = true;
			
			foreach($this->commands as $command) {
				
				// @ indicates a custom command
				if(substr($command->command, 0, 1) == '@') {
				
					// reset the start timer, because the time shoold start now
					// if called when the timer is not running starts to count the time
					switch($command->command){

						case '@resetTimer':

							$starttime = microtime(true);
							$timerRunning = true;
							break;

						case '@startTimer':
							
							if(!$timerRunning) {

								$starttime = microtime(true);
								$timerRunning = true;
							}
							break;

						case '@stopTimer': 
						
							if($timerRunning) {
								$timeLog[] = microtime(true) - $lastRound.' '.$command->comment;
								$lastRound = microtime(true);
								$time += microtime(true) - $starttime;
								$timerRunning = false;
							}
							break;

					}
					
					// continue with the next command
					continue;
				}
			
				$message = $this->sel->executeCommand($command);
				if($message != 'OK') {
			
					$this->sel->stop();
					$this->testSuccessful = false;
					$msg = 'An error occured: Command:'.$command->command.' at line '.$command->lineInFile.' in your commands. Message: '.$message;
					if(!empty($command->comment)) {
						
						$msg .= ' Comment: '.$command->comment."\n";
					}
					return array(false,$msg.' '.implode(':',$timeLog),0);
				}
			}
			
			// if timer is running, now stop it
			if($timerRunning) {
				$time += microtime(true) - $starttime;
			}
			
			if($this->testSuccessful) {
				return array(true, implode(':',$timeLog)  , $time);
			}
		}
		return array(false);
	}
  
  	function loadCommands() {
  	
  		$commandsLines = explode("\n",$this->commandsText);
  		if(empty($commandsLines)) {
  		
  			fwrite(STDERR,'An error occured: No commands were found!'."\n");
  			return false;
  		}
  		$lineNumber = 0;
  		
  		foreach($commandsLines as $command) {
  			$command = trim($command);
  			$firstChar = substr(trim($command),0,1);
  			if( !empty($command) && ($firstChar != '-' && $firstChar != '#' && $firstChar != ':' ) ) {
  			
  				if(!empty($com)) {
					$this->commands[] = $com;
				}
  				$commandText = substr($command,0,strpos($command,':'));
  				$commentText = substr($command,strpos($command,':') + 1);
  				$com = new tx_caretakerselenium_SeleniumCommand($commandText,$lineNumber + 1,$commentText);
  				$paramCount = 1;
  				
  			} elseif($firstChar == '-') {
  			
	  			if(!empty($paramCount) && $paramCount < 2) {
	  			
	  				if(!$com->addParam(trim(substr($command,1)))) {
	  				
	  					$paramCount++;
	  					fwrite(STDERR,'There are too many parameters for that command or wrong syntax at line'.$lineNumber.'.'."\n");
	  					return false;
	  				}
	  			}
	  		} elseif($firstChar == ':') {
	  			
	  			$com->addComment(trim(substr($command,1)));
	  		}
  			$lineNumber++;
  		}
  		return true;
  	}  
}

?>
