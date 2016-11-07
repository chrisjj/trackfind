<?php
/* trackfind - Show set tandas using given track
 INPUTS in querystring format in CLI argv[1] or CGI url
         * _SERVER[HTTP_REFERER] (sic) - URL of caller in folder containing set lists
         * tint = TINTdash of target track (if absent, test value used)
         * setmaxqty = limit for testing
 OUTPUTS:  * page showing
 							* this track
 							* if used in tandas
 							  * tandamates ordered most frequent first
								* for each tanda using this track
									 * tanda
									 * name of each set using it, link to set anchor at first matching tanda
							* else 
								* name of each set using it, link to set anchor at first matching tanda
								
 USAGE:  set track "uses" link: ?tint=<TINT>
*/
/* TO DO
* add download
* ? Revert to ANSI, converting aHD output back to ANSI and textual HTML entities for e.g. $nbsp;, &bull;
* Move to trackfind-1
* Create backup on tango.info
* Reference other recordings of same performance e.g. This track .. and these/this other track/tracks of the same performance ... has/have been  
* Tandamates order by set currently is first (latest?) use top. Verify accords with displayed set order (latest set top).
* Fix w3C validator complaints
* Test advanced_html_dom  for speed-up
* Fiduplicate id's from source
* For security, restrict parse_str get parameters to $SERVER
* Pass referrer in usage hrefs, and remove workaround
* Disallow invalid/undeclared referer, so to discourage direct linking to the impermanent appspot address.
* Find some way to replace the appsot address with in-site address.
* Limit set coverage to recent n? Might look odd from 'uses' of excluded sets
* FIX Code accepts invalid track rows e.g. having wrong number of cells.
 
* DONE
* Fix creation of default object (advanced_html_dom warning) at  $r->find('tr',0)->id=null  
* Implement tracelevels
* add trace=true command parameter, and time points
* Fix duplication in 'with' list due to "id=..." on some tracks
 */

// Querystring for server-based standalone test: /?_SERVER[HTTP_REFERER]=http://www.chrisjj.com/tango/cjjsets/2015-09-13_Tangueando_Practicando.html

// Output HEAD part inc. META before any trace ouput
?>
<!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01//EN' 'http://www.w3.org/TR/html4/strict.dtd'>
<head><meta http-equiv='Content-Type' content='text/html;charset=UTF-8'>
<?php
$starttime=microtime(true);

$simple=true;
$simple=false;
if($simple) include 'simple_html_dom.php';
else include 'advanced_html_dom.php';


function t1($msg)  {traceatlevel(1,$msg);} function t2($msg)  {traceatlevel(2,$msg);} function t3($msg)  {traceatlevel(3,$msg);}
function traceatlevel($eventlevel,$msg)
{
 global $trace,$starttime;
 if($trace>=$eventlevel)
 	print ("\n<p>TL".$eventlevel." ".number_format(microtime(true)-$starttime,3)."s: ".$msg."</p>");
}

function shorttitle ($t) { 
$t = htmlentities($t); // Fix for advanced_html_dom auto-decoding of entities
$t = preg_replace('~ +&nbsp;.*~','',$t);
return $t;
}
function isdance($genres) { return 0!=count(array_intersect(explode(' ',$genres),array('tango','vals','milonga','tango-milonga','candombe'))); } // TBD! Recode to use added class=dance and DOM 
function isexitorend($genres) { return 0!=count(array_intersect(explode(' ',$genres),array('exit','end'))); } // TBD! Recode to use added class=dance and DOM 
function isendingcumparsita($r) { return
							trim($r->find('td',3/*title*/)->innertext) == "La cumparsita" &&
							( $r->next_sibling() == null || isexitorend($r->next_sibling()->class) ) ;
							}

function getparm($name, $default = "") { return isset($_GET[$name]) ? $_GET[$name] : $default; }
function deepcopy($a) { return unserialize(serialize($a)); }
function submatch1($p,$s) { preg_match($p,$s,$matches);return $matches[1];}
// Get parameters
// Requires arg to omit $ and single-quote e.g. $_SERVER['HTTP_REFERER'] -> _SERVER[HTTP_REFERER]
if(isset($argv[0])) // Is being run from CLI
{
  if(isset($argv[1])) parse_str($argv[1]); // From CLI, fill option vars inc. $_SERVER elements
}
else
{
  parse_str($_SERVER['QUERY_STRING']); // Fill $_SERVER elements from CGI querystring
  $_SERVER['HTTP_REFERER']='http://www.chrisjj.com/tango/cjjsets/dummy.html'; // TBD! Temp fix for loss of referrer on uses links in trackinfo page
}
t1("Start");

// Unit testing options
switch(13)
{ case 0: $testtint = '02480002928225-1-629';break; // curtain
 	case 1: $testtint = '00743218880827-1-6'  ;break; // dance - El porteñito
 	case 2: $testtint = '08427328130905-1-1'  ;break; // dance - Quinteto Don Pancho	 
 	case 3: $testtint = '00828766933420-1-3'  ;break; // dance - Dime mi amor - many uses
 	case 4: $testtint = '00743216333820-1-18' ;break; // dance - used in tanda with adjacent Cumparsitas.
 	case 5: $testtint = '09999902659281-1-8'  ;break; // dance - Cumparsita.
 	case 6: $testtint = '02480002928621-1-16' ;break; // dance - Osvaldo Fresedo, singer Roberto Ray	Dulce amargura showing inclusion of non-CJ set.h ttp://archive.is/8xm1K
 	case 7: $testtint = '00828766124224-1-3'  ;break; // dance - for Sinsabor, repeated inside tanda
 	case 8: $testtint = '08427328130950-1-4'  ;break; // dance - for Me voy a baraja suffering id= dup bug on Alas Rotas http://i.imgur.com/xIjFDAC.png
 	case 9: $testtint = '00724383741328-1-9'  ;break; // dance - for Jamás retornarás suffering ??? dup bug on Si tú quisieras   http://i.imgur.com/xIjFDAC.png
	case 10: $testtint = '04024236030026-1-7'  ;break; // dance - no subst clip msg 	
	case 11: $testtint = '00828766124224-1-3'  ;break; // dance - subst clip msg 	
	case 12: $testtint = '08427328131018-1-16'  ;break; // dance - El Morochito fold fail
 	case 13: $testtint = '07798108080460-1-7'  ;break; // dance - small
 	
}
$setpertintmaxqty = 999;

// Set $setdir to directory containing sets files

$setdir = dirname($_SERVER['HTTP_REFERER'])."/"; // Only forward slash works for Windows CLI and web CGI
 
$tint = getparm('tint',$testtint);
 
$datafilepath = $setdir.'searchdata.bin';

$out=array();
$searchdata = unserialize(gzuncompress(file_get_contents($datafilepath)));
$tintsets= $searchdata['tracksetnames'];
$tandamates = array();
$targ = "UNSET!";
if( !isset($tintsets[$tint]) ) 
{ $out[]="No uses found.";
	exit();
}

$setnametotitleandtanda=array();
$setseqnum=0;
$substmsgreqd=false;
foreach( array_slice(array_keys($tintsets[$tint]),0,getparm($setmaxqty,9999)) as $setname)
{ 
 // Get set
	$set = str_get_html($searchdata['setnamesets'][$setname]); 
	// Get target track // Considers first occurence only - ignores e.g. second tanda using same track
	$targ = $set->find('tr[id=id'.$tint.']',0);

	// Colourise target track
	$t = $targ->find('td',0);
	foreach(array('white','grey','grey','grey','grey') as $col)
	{ $t->class .= " $col";
		$t = $t->next_sibling();
	}
	// Since target's 'uses' link goes to this page, remove that link
	$targ->find('td',/*uses*/7)->innertext='';

	// Get tanda
	// WARNING: prev_ & next_sibling include comments - fortunately none preset in sets 
	$r = $targ;
	if( isdance($r->class) && !isendingcumparsita($r) )
	{ // Dance track - output tanda
		$tanda = "";
		while($r != null && ($r->tag!='tr' || isdance($r->class)) ) // Back up to first non-dance track
		$r=$r->prev_sibling();
		$r=$r->next_sibling(); // forward to first tandatrack
		while($r != null && isdance($r->class) ) // forward through tanda
		{	// WARNING: $r->find('td') incudes td's of subsequent rows!
			// WARNING: Code accepts invalid track rows e.g. having wrong number of cells.
			if( !isendingcumparsita($r)
				|| // Unless it is the target
				trim($targ->find('td',/*title*/3)->innertext) == "La cumparsita" 
			)
			{ // Include track in tanda
				// Time cell (already hidden in style)
				$r->find('td',/*time*/0)->innertext='00:00'; // Set to constant value for folding

				// Delete id to avoid:
					// * non-folding of IDed (first) and unIDed (rare subsequent) instances of same track in one set - test case 6, Sinsabor
					// * HTML-invalid reuse of id
				$r->id=null; 
				$tanda .= $r."\n";               

				// Record (folding) tandamate [tanda]->list position
				if($r != $targ) 
				{ $s = (string)$r;					
					if(!isset($tandamates [$s])) $tandamates [$s] = $setseqnum++; // Make track the (first) set of which is later-dated be lower on list
					$tandamates [$s] -= 1000; // Make most used track near top
				}
				
				// substmsgreqd
				if (preg_match("~\*~",$r->find('td',/*clip*/6)->innertext)) $substmsgreqd = true;

			}
			$r=$r->next_sibling();
		}
	}
	else
	{ // Non-dance track - output one-track 'tanda'
		$targ->find('td',0)->innertext=''; // Empty the time cell
		$tanda = $targ."\n";
	}

	// Set name
	$settitle = shorttitle($set->find('title',0)->innertext);
	$setnametotitleandtanda[$setname]=array('title'=>$settitle,'tanda'=>$tanda);
}

$tandatosetnameandtitle=array();
foreach($setnametotitleandtanda as $setname => $settitleandtanda)
{
	$tandatosetnameandtitle[$settitleandtanda['tanda']][]=array('name'=>$setname,'title'=>$settitleandtanda['title']);
}

$out[]="<table>";
$out[]=$targ;
$out[]="<tr><td colspan=999><br>has been used";
if(count($tandamates)!=0)
{	$out[]=" with<br><br>";	
	asort($tandamates); // most used first, within which latest set first
	$out[]=implode("\n",array_keys($tandamates));
	$out[]="<tr><td colspan=999><br>";
}
$out[]=" in<br><br>";

$spacer="style='bottom-padding:10px'";
foreach($tandatosetnameandtitle as $tanda => $setnameandtitles)
{ 
	foreach ($setnameandtitles as $setnameandtitle)
	{ 
		$out[]="<tr ".$spacer."><td colspan=7>".$setnameandtitle['title'];
		$out[]="<td align='right'><a href='".$setdir.$setnameandtitle['name']."#id".$tint."'>list</a>";
		$spacer="";
	}
	$spacer="style='border-top:16px solid white' ";
	if(count($tandamates)!=0) ($out[]=$tanda);
}

$out[]="<tr><td colspan=999 class='bottomrule'>\n";
$out[]='</table>';
  
if($substmsgreqd) $out[]="<div><br><a name='substmsg'></a>* The precise track played has no available info/clip, so this info/clip is from a near-matching track of the same performance.</div>";

print ('<title>Uses of '.$tint.'</title>');
?>
<style type='text/css'>
body {font-family:Arial,Verdana,Sans-serif;font-size:small}
div { width:750px }
table { border-collapse:collapse } 
td { padding-top:2px;padding-bottom:2px;padding-right:5px;padding-left:5px; white-space: nowrap}
td.grey { background-color:rgb(232,232,232) }
td.white { background-color:white }
td.bottomrule {border-bottom-style:solid;border-width:1px;padding-bottom:10px}
td:nth-child(-n+2),td:nth-child(n+5)  { width:1% }
tr[class*="track-"] td:nth-child(1) { visibility:hidden }
</style>
</head><body>
<div>
<?php
	print (implode("\n",$out));
  t1("End");
?>
</div>