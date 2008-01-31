<?php
/***************************************************************
*  Copyright notice
*
*  (c) 1999-2005 Kasper Skaarhoj (kasperYYYY@typo3.com)
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * Core functions for cleaning and analysing
 *
 * $Id$
 *
 * @author	Kasper Sk�rh�j <kasperYYYY@typo3.com>
 */
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *   71: class tx_lowlevel_cleaner_core extends t3lib_cli
 *   88:     function tx_lowlevel_cleaner_core()
 *
 *              SECTION: CLI functionality
 *  134:     function cli_main($argv)
 *  193:     function cli_referenceIndexCheck()
 *  228:     function cli_noExecutionCheck($matchString)
 *  251:     function cli_printInfo($header,$res)
 *
 *              SECTION: Page tree traversal
 *  331:     function genTree($rootID,$depth=1000,$echoLevel=0,$callBack='')
 *  369:     function genTree_traverse($rootID,$depth,$echoLevel=0,$callBack='',$versionSwapmode='',$rootIsVersion=0,$accumulatedPath='')
 *
 *              SECTION: Helper functions
 *  554:     function infoStr($rec)
 *
 * TOTAL FUNCTIONS: 8
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */


require_once(PATH_t3lib.'class.t3lib_admin.php');
require_once(PATH_t3lib.'class.t3lib_cli.php');



/**
 * Core functions for cleaning and analysing
 *
 * @author	Kasper Sk�rh�j <kasperYYYY@typo3.com>
 * @package TYPO3
 * @subpackage tx_lowlevel
 */
class tx_lowlevel_cleaner_core extends t3lib_cli {

	var $genTree_traverseDeleted = TRUE;
	var $genTree_traverseVersions = TRUE;



	var $label_infoString = 'The list of records is organized as [table]:[uid]:[field]:[flexpointer]:[softref_key]';
	var $pagetreePlugins = array();
	var $cleanerModules = array();


	/**
	 * Constructor
	 *
	 * @return	void
	 */
	function tx_lowlevel_cleaner_core()	{

			// Running parent class constructor
		parent::t3lib_cli();

		$this->cleanerModules = (array)$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['lowlevel']['cleanerModules'];

			// Adding options to help archive:
		$this->cli_options[] = array('-r', 'Execute this tool, otherwise help is shown');
		$this->cli_options[] = array('-v level', 'Verbosity level 0-3', "The value of level can be:\n  0 = all output\n  1 = info and greater (default)\n  2 = warnings and greater\n  3 = errors");
		$this->cli_options[] = array('--refindex mode', 'Mode for reference index handling for operations that require a clean reference index ("update"/"ignore")', 'Options are "check" (default), "update" and "ignore". By default, the reference index is checked before running analysis that require a clean index. If the check fails, the analysis is not run. You can choose to bypass this completely (using value "ignore") or ask to have the index updated right away before the analysis (using value "update")');
		$this->cli_options[] = array('--AUTOFIX [testName]', 'Repairs errors that can be automatically fixed.', 'Only add this option after having run the test without it so you know what will happen when you add this option! The optional parameter "[testName]" works for some tool keys to limit the fixing to a particular test.');
		$this->cli_options[] = array('--dryrun', 'With --AUTOFIX it will only simulate a repair process','You may like to use this to see what the --AUTOFIX option will be doing. It will output the whole process like if a fix really occurred but nothing is in fact happening');
		$this->cli_options[] = array('--YES', 'Implicit YES to all questions','Use this with EXTREME care. The option "-i" is not affected by this option.');
		$this->cli_options[] = array('-i', 'Interactive','Will ask you before running the AUTOFIX on each element.');
		$this->cli_options[] = array('--filterRegex expr', 'Define an expression for preg_match() that must match the element ID in order to auto repair it','The element ID is the string in quotation marks when the text \'Cleaning ... in "ELEMENT ID"\'. "expr" is the expression for preg_match(). To match for example "Nature3.JPG" and "Holiday3.JPG" you can use "/.*3.JPG/". To match for example "Image.jpg" and "Image.JPG" you can use "/.*.jpg/i". Try a --dryrun first to see what the matches are!');
		$this->cli_options[] = array('--showhowto', 'Displays HOWTO file for cleaner script.');

			// Setting help texts:
		$this->cli_help['name'] = 'lowlevel_cleaner -- Analysis and clean-up tools for TYPO3 installations';
		$this->cli_help['synopsis'] = 'toolkey ###OPTIONS###';
		$this->cli_help['description'] = "Dispatches to various analysis and clean-up tools which can plug into the API of this script. Typically you can run tests that will take longer than the usual max execution time of PHP. Such tasks could be checking for orphan records in the page tree or flushing all published versions in the system. For the complete list of options, please explore each of the 'toolkey' keywords below:\n\n  ".implode("\n  ",array_keys($this->cleanerModules));
		$this->cli_help['examples'] = "/.../cli_dispatch.phpsh lowlevel_cleaner missing_files -s -r\nThis will show you missing files in the TYPO3 system and only report back if errors were found.";
		$this->cli_help['author'] = "Kasper Skaarhoej, (c) 2006";
	}









	/**************************
	 *
	 * CLI functionality
	 *
	 *************************/

	/**
	 * CLI engine
	 *
	 * @param	array		Command line arguments
	 * @return	string
	 */
	function cli_main($argv) {

			// Force user to admin state and set workspace to "Live":
		$GLOBALS['BE_USER']->user['admin'] = 1;
		$GLOBALS['BE_USER']->setWorkspace(0);

			// Print Howto:
		if ($this->cli_isArg('--showhowto'))	{
			$howto = t3lib_div::getUrl(t3lib_extMgm::extPath('lowlevel').'HOWTO_clean_up_TYPO3_installations.txt');
			echo wordwrap($howto,120).chr(10);
			exit;
		}

			// Print help
		$analysisType = (string)$this->cli_args['_DEFAULT'][1];
		if (!$analysisType)	{
			$this->cli_validateArgs();
			$this->cli_help();
			exit;
		}

			// Analysis type:
		switch((string)$analysisType)    {
			default:
				if (is_array($this->cleanerModules[$analysisType]))	{
					$cleanerMode = &t3lib_div::getUserObj($this->cleanerModules[$analysisType][0]);
					$cleanerMode->cli_validateArgs();

					if ($this->cli_isArg('-r'))	{	// Run it...
						if (!$cleanerMode->checkRefIndex || $this->cli_referenceIndexCheck())	{
							$res = $cleanerMode->main();
							$this->cli_printInfo($analysisType, $res);

								// Autofix...
							if ($this->cli_isArg('--AUTOFIX'))	{
								if ($this->cli_isArg('--YES') || $this->cli_keyboardInput_yes("\n\nNOW Running --AUTOFIX on result. OK?".($this->cli_isArg('--dryrun')?' (--dryrun simulation)':'')))	{
									$cleanerMode->main_autofix($res);
								} else {
									$this->cli_echo("ABORTING AutoFix...\n",1);
								}
							}
						}
					} else {	// Help only...
						$cleanerMode->cli_help();
						exit;
					}
				} else {
					$this->cli_echo("ERROR: Analysis Type '".$analysisType."' is unknown.\n",1);
					exit;
				}
			break;
		}
	}

	/**
	 * Checks reference index
	 *
	 * @return	boolean		TRUE if reference index was OK (either OK, updated or ignored)
	 */
	function cli_referenceIndexCheck()	{

			// Reference index option:
		$refIndexMode = isset($this->cli_args['--refindex']) ? $this->cli_args['--refindex'][0] : 'check';
		if (!t3lib_div::inList('update,ignore,check', $refIndexMode))	{
			$this->cli_echo("ERROR: Wrong value for --refindex argument.\n",1);
			exit;
		}

		switch($refIndexMode)	{
			case 'check':
			case 'update':
				$refIndexObj = t3lib_div::makeInstance('t3lib_refindex');
				list($headerContent,$bodyContent,$errorCount) = $refIndexObj->updateIndex($refIndexMode=='check',$this->cli_echo());

				if ($errorCount && $refIndexMode=='check')	{
					$ok = FALSE;
					$this->cli_echo("ERROR: Reference Index Check failed! (run with '--refindex update' to fix)\n",1);
				} else {
					$ok = TRUE;
				}
			break;
			case 'ignore':
				$this->cli_echo("Reference Index Check: Bypassing reference index check...\n");
				$ok = TRUE;
			break;
		}

		return $ok;
	}

	/**
	 * @param	[type]		$matchString: ...
	 * @return	string		If string, it's the reason for not executing. Returning FALSE means it should execute.
	 */
	function cli_noExecutionCheck($matchString)	{

			// Check for filter:
		if ($this->cli_isArg('--filterRegex') && $regex = $this->cli_argValue('--filterRegex',0))	{
			if (!preg_match($regex,$matchString))	return 'BYPASS: Filter Regex "'.$regex.'" did not match string "'.$matchString.'"';
		}
			// Check for interactive mode
		if ($this->cli_isArg('-i'))	{
			if (!$this->cli_keyboardInput_yes(' EXECUTE?'))	{
				return 'BYPASS...';
			}
		}
			// Check for
		if ($this->cli_isArg('--dryrun'))	return 'BYPASS: --dryrun set';
	}

	/**
	 * Formats a result array from a test so it fits output in the shell
	 *
	 * @param	string		name of the test (eg. function name)
	 * @param	array		Result array from an analyze function
	 * @return	void		Outputs with echo - capture content with output buffer if needed.
	 */
	function cli_printInfo($header,$res)	{

		$detailLevel = t3lib_div::intInRange($this->cli_isArg('-v') ? $this->cli_argValue('-v') : 1,0,3);
		$silent = !$this->cli_echo();

		$severity = array(
			0 => 'MESSAGE',
			1 => 'INFO',
			2 => 'WARNING',
			3 => 'ERROR',
		);

			// Header output:
		if ($detailLevel <= 1)	{
			$this->cli_echo(
				"*********************************************\n".
				$header."\n".
				"*********************************************\n");
			$this->cli_echo(wordwrap(trim($res['message'])).chr(10).chr(10));
		}

			// Traverse headers for output:
		if (is_array($res['headers'])) {
			foreach($res['headers'] as $key => $value)	{

				if ($detailLevel <= intval($value[2]))	{
					if (is_array($res[$key]) && (count($res[$key]) || !$silent)) {

							// Header and explanaion:
						$this->cli_echo('---------------------------------------------'.chr(10),1);
						$this->cli_echo('['.$header.']'.chr(10),1);
						$this->cli_echo($value[0].' ['.$severity[$value[2]].']'.chr(10),1);
						$this->cli_echo('---------------------------------------------'.chr(10),1);
						if (trim($value[1]))	{
							$this->cli_echo('Explanation: '.wordwrap(trim($value[1])).chr(10).chr(10),1);
						}
					}

						// Content:
					if (is_array($res[$key]))	{
						if (count($res[$key]))	{
							if ($this->cli_echo('',1)) { print_r($res[$key]); }
						} else {
							$this->cli_echo('(None)'.chr(10).chr(10));
						}
					} else {
						$this->cli_echo($res[$key].chr(10).chr(10));
					}
				}
			}
		}
	}












	/**************************
	 *
	 * Page tree traversal
	 *
	 *************************/

	/**
	 * Traverses the FULL/part of page tree, mainly to register ALL validly connected records (to find orphans) but also to register deleted records, versions etc.
	 * Output (in $this->recStats) can be useful for multiple purposes.
	 *
	 * @param	integer		Root page id from where to start traversal. Use "0" (zero) to have full page tree (necessary when spotting orphans, otherwise you can run it on parts only)
	 * @param	integer		Depth to traverse. zero is do not traverse at all. 1 = 1 sublevel, 1000= 1000 sublevels (all...)
	 * @param	boolean		If >0, will echo information about the traversal process.
	 * @param	string		Call back function (from this class or subclass)
	 * @return	void
	 */
	function genTree($rootID,$depth=1000,$echoLevel=0,$callBack='')	{

			// Initialize:
		$this->workspaceIndex = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('uid,title','sys_workspace','1=1'.t3lib_BEfunc::deleteClause('sys_workspace'),'','','','uid');
		$this->workspaceIndex[-1] = TRUE;
		$this->workspaceIndex[0] = TRUE;

		$this->recStats = array(
			'all' => array(),									// All records connected in tree including versions (the reverse are orphans). All Info and Warning categories below are included here (and therefore safe if you delete the reverse of the list)
			'deleted' => array(),								// Subset of "alL" that are deleted-flagged [Info]
			'versions' => array(),								// Subset of "all" which are offline versions (pid=-1). [Info]
			'versions_published' => array(),					// Subset of "versions" that is a count of 1 or more (has been published) [Info]
			'versions_liveWS' => array(),						// Subset of "versions" that exists in live workspace [Info]
			'versions_lost_workspace' => array(),				// Subset of "versions" that doesn't belong to an existing workspace [Warning: Fix by move to live workspace]
			'versions_inside_versioned_page' => array(),		// Subset of "versions" This is versions of elements found inside an already versioned branch / page. In real life this can work out, but is confusing and the backend should prevent this from happening to people. [Warning: Fix by deleting those versions (or publishing them)]
			'illegal_record_under_versioned_page' => array(),	// If a page is "element" or "page" version and records are found attached to it, they might be illegally attached, so this will tell you. [Error: Fix by deleting orphans since they are not registered in "all" category]
			'misplaced_at_rootlevel' => array(),				// Subset of "all": Those that should not be at root level but are. [Warning: Fix by moving record into page tree]
			'misplaced_inside_tree' => array(),					// Subset of "all": Those that are inside page tree but should be at root level [Warning: Fix by setting PID to zero]
		);

			// Start traversal:
		$this->genTree_traverse($rootID,$depth,$echoLevel,$callBack);
		
			// Sort recStats (for diff'able displays)
		foreach($this->recStats as $kk => $vv)	{
			foreach($this->recStats[$kk] as $tables => $recArrays)	{
				ksort($this->recStats[$kk][$tables]);
			}
			ksort($this->recStats[$kk]);
		}

		if ($echoLevel>0)	echo chr(10).chr(10);
	}

	/**
	 * Recursive traversal of page tree:
	 *
	 * @param	integer		Page root id (must be online, valid page record - or zero for page tree root)
	 * @param	integer		Depth
	 * @param	integer		Echo Level
	 * @param	string		Call back function (from this class or subclass)
	 * @param	string		DON'T set from outside, internal. (indicates we are inside a version of a page)
	 * @param	integer		DON'T set from outside, internal. (1: Indicates that rootID is a version of a page, 2: ...that it is even a version of a version (which triggers a warning!)
	 * @param	string		Internal string that accumulates the path
	 * @return	void
	 * @access private
	 */
	function genTree_traverse($rootID,$depth,$echoLevel=0,$callBack='',$versionSwapmode='',$rootIsVersion=0,$accumulatedPath='')	{

			// Register page:
		$this->recStats['all']['pages'][$rootID] = $rootID;
		$pageRecord = t3lib_BEfunc::getRecordRaw('pages','uid='.intval($rootID),'deleted,title,t3ver_count,t3ver_wsid');
		$accumulatedPath.='/'.$pageRecord['title'];

			// Register if page is deleted:
		if ($pageRecord['deleted'])	{
			$this->recStats['deleted']['pages'][$rootID] = $rootID;
		}
			// If rootIsVersion is set it means that the input rootID is that of a version of a page. See below where the recursive call is made.
		if ($rootIsVersion)	{
			$this->recStats['versions']['pages'][$rootID] = $rootID;
			if ($pageRecord['t3ver_count']>=1 && $pageRecord['t3ver_wsid']==0)	{	// If it has been published and is in archive now...
				$this->recStats['versions_published']['pages'][$rootID] = $rootID;
			}
			if ($pageRecord['t3ver_wsid']==0)	{	// If it has been published and is in archive now...
				$this->recStats['versions_liveWS']['pages'][$rootID] = $rootID;
			}
			if (!isset($this->workspaceIndex[$pageRecord['t3ver_wsid']]))	{	// If it doesn't belong to a workspace...
				$this->recStats['versions_lost_workspace']['pages'][$rootID] = $rootID;
			}
			if ($rootIsVersion==2)	{	// In case the rootID is a version inside a versioned page
				$this->recStats['versions_inside_versioned_page']['pages'][$rootID] = $rootID;
			}
		}

		if ($echoLevel>0)
			echo chr(10).$accumulatedPath.' ['.$rootID.']'.
				($pageRecord['deleted'] ? ' (DELETED)':'').
				($this->recStats['versions_published']['pages'][$rootID] ? ' (PUBLISHED)':'')
				;
		if ($echoLevel>1 && $this->recStats['versions_lost_workspace']['pages'][$rootID])
			echo chr(10).'	ERROR! This version belongs to non-existing workspace ('.$pageRecord['t3ver_wsid'].')!';
		if ($echoLevel>1 && $this->recStats['versions_inside_versioned_page']['pages'][$rootID])
			echo chr(10).'	WARNING! This version is inside an already versioned page or branch!';

			// Call back:
		if ($callBack)	{
			$this->$callBack('pages',$rootID,$echoLevel,$versionSwapmode,$rootIsVersion);
		}

			// Traverse tables of records that belongs to page:
		foreach($GLOBALS['TCA'] as $tableName => $cfg)	{
			if ($tableName!='pages') {

					// Select all records belonging to page:
				$resSub = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
					'uid'.($GLOBALS['TCA'][$tableName]['ctrl']['delete']?','.$GLOBALS['TCA'][$tableName]['ctrl']['delete']:''),
					$tableName,
					'pid='.intval($rootID).
						($this->genTree_traverseDeleted ? '' : t3lib_BEfunc::deleteClause($tableName))
				);

				$count = $GLOBALS['TYPO3_DB']->sql_num_rows($resSub);
				if ($count)	{
					if ($echoLevel==2)	echo chr(10).'	\-'.$tableName.' ('.$count.')';
				}

				while ($rowSub = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($resSub))	{
					if ($echoLevel==3)	echo chr(10).'	\-'.$tableName.':'.$rowSub['uid'];

						// If the rootID represents an "element" or "page" version type, we must check if the record from this table is allowed to belong to this:
					if ($versionSwapmode=='SWAPMODE:-1' || ($versionSwapmode=='SWAPMODE:0' && !$GLOBALS['TCA'][$tableName]['ctrl']['versioning_followPages']))	{
							// This is illegal records under a versioned page - therefore not registered in $this->recStats['all'] so they should be orphaned:
						$this->recStats['illegal_record_under_versioned_page'][$tableName][$rowSub['uid']] = $rowSub['uid'];
						if ($echoLevel>1)	echo chr(10).'		ERROR! Illegal record ('.$tableName.':'.$rowSub['uid'].') under versioned page!';
					} else {
						$this->recStats['all'][$tableName][$rowSub['uid']] = $rowSub['uid'];

							// Register deleted:
						if ($GLOBALS['TCA'][$tableName]['ctrl']['delete'] && $rowSub[$GLOBALS['TCA'][$tableName]['ctrl']['delete']])	{
							$this->recStats['deleted'][$tableName][$rowSub['uid']] = $rowSub['uid'];
							if ($echoLevel==3)	echo ' (DELETED)';
						}

							// Check location of records regarding tree root:
						if (!$GLOBALS['TCA'][$tableName]['ctrl']['rootLevel'] && $rootID==0) {
							$this->recStats['misplaced_at_rootlevel'][$tableName][$rowSub['uid']] = $rowSub['uid'];
							if ($echoLevel>1)	echo chr(10).'		ERROR! Misplaced record ('.$tableName.':'.$rowSub['uid'].') on rootlevel!';
						}
						if ($GLOBALS['TCA'][$tableName]['ctrl']['rootLevel']==1 && $rootID>0) {
							$this->recStats['misplaced_inside_tree'][$tableName][$rowSub['uid']] = $rowSub['uid'];
							if ($echoLevel>1)	echo chr(10).'		ERROR! Misplaced record ('.$tableName.':'.$rowSub['uid'].') inside page tree!';
						}

							// Traverse plugins:
						if ($callBack)	{
							$this->$callBack($tableName,$rowSub['uid'],$echoLevel,$versionSwapmode,$rootIsVersion);
						}

							// Add any versions of those records:
						if ($this->genTree_traverseVersions)	{
							$versions = t3lib_BEfunc::selectVersionsOfRecord($tableName, $rowSub['uid'], 'uid,t3ver_wsid,t3ver_count'.($GLOBALS['TCA'][$tableName]['ctrl']['delete']?','.$GLOBALS['TCA'][$tableName]['ctrl']['delete']:''), 0, TRUE);
							if (is_array($versions))	{
								foreach($versions as $verRec)	{
									if (!$verRec['_CURRENT_VERSION'])	{
										if ($echoLevel==3)	echo chr(10).'		\-[#OFFLINE VERSION: WS#'.$verRec['t3ver_wsid'].'/Cnt:'.$verRec['t3ver_count'].'] '.$tableName.':'.$verRec['uid'].')';
										$this->recStats['all'][$tableName][$verRec['uid']] = $verRec['uid'];

											// Register deleted:
										if ($GLOBALS['TCA'][$tableName]['ctrl']['delete'] && $verRec[$GLOBALS['TCA'][$tableName]['ctrl']['delete']])	{
											$this->recStats['deleted'][$tableName][$verRec['uid']] = $verRec['uid'];
											if ($echoLevel==3)	echo ' (DELETED)';
										}

											// Register version:
										$this->recStats['versions'][$tableName][$verRec['uid']] = $verRec['uid'];
										if ($verRec['t3ver_count']>=1 && $verRec['t3ver_wsid']==0)	{	// Only register published versions in LIVE workspace (published versions in draft workspaces are allowed)
											$this->recStats['versions_published'][$tableName][$verRec['uid']] = $verRec['uid'];
											if ($echoLevel==3)	echo ' (PUBLISHED)';
										}
										if ($verRec['t3ver_wsid']==0)	{
											$this->recStats['versions_liveWS'][$tableName][$verRec['uid']] = $verRec['uid'];
										}
										if (!isset($this->workspaceIndex[$verRec['t3ver_wsid']]))	{
											$this->recStats['versions_lost_workspace'][$tableName][$verRec['uid']] = $verRec['uid'];
											if ($echoLevel>1)	echo chr(10).'		ERROR! Version ('.$tableName.':'.$verRec['uid'].') belongs to non-existing workspace ('.$verRec['t3ver_wsid'].')!';
										}
										if ($versionSwapmode)	{	// In case we are inside a versioned branch, there should not exists versions inside that "branch".
											$this->recStats['versions_inside_versioned_page'][$tableName][$verRec['uid']] = $verRec['uid'];
											if ($echoLevel>1)	echo chr(10).'		ERROR! This version ('.$tableName.':'.$verRec['uid'].') is inside an already versioned page or branch!';
										}

											// Traverse plugins:
										if ($callBack)	{
											$this->$callBack($tableName,$verRec['uid'],$echoLevel,$versionSwapmode,$rootIsVersion);
										}
									}
								}
							}
							unset($versions);
						}
					}
				}
			}
		}
		unset($resSub);
		unset($rowSub);

			// Find subpages to root ID and traverse (only when rootID is not a version or is a branch-version):
		if (!$versionSwapmode || $versionSwapmode=='SWAPMODE:1')	{
			if ($depth>0)	{
				$depth--;
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
					'uid',
					'pages',
					'pid='.intval($rootID).
						($this->genTree_traverseDeleted ? '' : t3lib_BEfunc::deleteClause('pages')),
					'',
					'sorting'
				);
				while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
					$this->genTree_traverse($row['uid'],$depth,$echoLevel,$callBack,$versionSwapmode,0,$accumulatedPath);
				}
			}

				// Add any versions of pages
			if ($rootID>0 && $this->genTree_traverseVersions)	{
				$versions = t3lib_BEfunc::selectVersionsOfRecord('pages', $rootID, 'uid,t3ver_oid,t3ver_wsid,t3ver_count,t3ver_swapmode', 0, TRUE);
				if (is_array($versions))	{
					foreach($versions as $verRec)	{
						if (!$verRec['_CURRENT_VERSION'])	{
							$this->genTree_traverse($verRec['uid'],$depth,$echoLevel,$callBack,'SWAPMODE:'.t3lib_div::intInRange($verRec['t3ver_swapmode'],-1,1),$versionSwapmode?2:1,$accumulatedPath.' [#OFFLINE VERSION: WS#'.$verRec['t3ver_wsid'].'/Cnt:'.$verRec['t3ver_count'].']');
						}
					}
				}
			}
		}
	}








	/**************************
	 *
	 * Helper functions
	 *
	 *************************/

	/**
	 * Compile info-string
	 *
	 * @param	array		Input record from sys_refindex
	 * @return	string		String identifying the main record of the reference
	 */
	function infoStr($rec)	{
		return $rec['tablename'].':'.$rec['recuid'].':'.$rec['field'].':'.$rec['flexpointer'].':'.$rec['softref_key'].($rec['deleted'] ? ' (DELETED)':'');
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/lowlevel/class.tx_lowlevel_cleaner.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/lowlevel/class.tx_lowlevel_cleaner.php']);
}
?>
