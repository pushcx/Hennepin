#!/usr/local/bin/php
<?PHP
/*
Hennepin requires PHP to have been built with --enable-sockets
Just read through the Configuration section to run Hennepin
CVS info: $Id: hennepin.php,v 1.46 2004/12/14 18:19:59 malaprop Exp $
*/

// Configuration ///////////////////////////////////////////

// IP address to listen on, 0.0.0.0 for all
// $IP='127.0.0.1'; // commented out for socket_create_listen (below)

// port to listen on
$P=1812;

// "WARN" comments note that PHP warnings can be removed by uncommenting the
// following code. As they're generally harmless, the bytes were instead saved.

// Also, depending on what error_reporting is set to, PHP may get real spammy
// with how I've played fast-and-loose with syntax. You may wish to set:
// error_reporting = E_ALL & ~E_NOTICE
// in your php.ini, or uncomment:
// error_reporting(E_ALL ^ E_NOTICE);

// to start Hennepin, make the first line of this file the same as 
// `which php` -- it'll probably be /usr/bin/php or /usr/local/bin/php
// then: ./hennepin

// When coding, smashing global vars with uppercase names will either
// 1. crash the mud or 2. introduce subtle errors that will much later cause #1
// So be careful with them - they're documented for a reason!

// Data Structures /////////////////////////////////////////

/* $C -- connections, an array of the connected users
Every connection is represented by an array in $C. Upon connection, they're
indexed by socket id # (which is not a valid socket resource, because it's a
key and not a value). As soon as they provide a name, the entry is replaced by
one with their name to save the hassle of looking them up all the time.

* Networking includes an [ip] for their ip address, and [s] for socket
  resource.
* [ib] is their input buffer, it should not be manipulated except by the
  existing network code 
* [in] points to the function that get the next line of input, w/o \n.  See:
  http://us4.php.net/manual/en/functions.variable-functions.php
* [name], [pwd], and other variables are game info. [l] is last command
* [b] is whether they're busy doing something -- so any commands issued will be delayed
* [hp] hit points, [vit] vitality, [str] strength, [ste] stealth, [per] perception
*/
$C=array();

/* $U -- load up user db
$U has the same structures as $C and is the store for players.
When a player is loaded, their old [ip] and [s] need to be removed first.
[a] is the area they're in; [r] is the room; [c] is if they support ansi
*/
$U=i('users');

/* $W -- the world data
first-level index is network id, which has these keys:

* [name] -- name of network owner
* [n] -- array of the nodes, indexed by [name]
	* [type] -- the type of node
        * [vit] -- vitality of the node
	* [hp] -- hp of the node
* [c] -- array of connections between nodes, indexed by [name]
	* [f] -- node id from
	* [t] -- node id to
        * [bi] -- boolean for bidirectionality
        * [ste] -- stealth of the node
*/
$W=i('world');

/* $V -- an array of verbs
Hennepin can load code at runtime, creating verbs
$V is an array of callable functions indexed by name

No need to explicitly declare it, so we'll save the bytes
WARN
*/
//$V=array();

// $D -- current working directory
$D=getcwd();

// Functions ///////////////////////////////////////////////

/* w -- short for socket_write, sends a message to one connection
   Because I can't seem to get character mode, it adds a \n for you.
   Colors: do 0n for dark and 1n for light, n may be:
	0 - black (useless, really)
	1 - red
	2 - green
	3 - yellow
	4 - blue
	5 - magenta
	6 - cyan
	7 - gray
*/
function w($s,$m){
	global$C;

	// do ansi color replacements and
	// linebreak (PHP default is 75, and that's good enough)
	if($C[$s][c])
		socket_write($C[$s][s],preg_replace('/~(\d)(\d)/',chr(27).'[\1;3\2m',wordwrap("$m~07\n")));
	else
		socket_write($C[$s][s],preg_replace('/~(\d\d)/','',wordwrap("$m\n")));
}

// a -- announce a string to all players
function a($m){
	global$C;

	foreach($C as$s=>$p)w($s,$m);	// just loop over everybody
}

// b -- send a string to all in a room but the player
function b($s,$m){
	global$C;
	foreach($C as$n=>$p){
		// if not the player, and in the same area and room
		if($s!=$n&&$C[$s][a]==$p[a]&&$C[$s][r]==$p[r])
			w($n,$m);	// then give them the message
	}
}

// n -- network say -- send a message to people on the same network
function n($s,$m){
	global$C;
	foreach($C as$n=>$p){
		if($C[$s][a]==$p[a])
			w($n,$m);	// then give them the message
	}
	
}

// d -- disconnect socket and save player
function d($s){
	global$C,$U;

	$n=$C[$s][name];		// save their name for printing

	$U[$n]=$C[$s];			// save their character to $U

	l("disc: {$C[$s][ip]} $n");	// log the event

	socket_shutdown($C[$s][s]);	// clean up network state
	socket_close($C[$s][s]);
	unset($C[$s]);			// remove them from connections
	su();				// save the userfile

	if($n)a("~12$n quit");		// if they had logged in, announce departure
}

// l -- timestamped log to console
function l($m){echo date('Y-m-d H:i:s ')."$m\n";}

/* h -- halt Hennepin, PHP will clean up sockets for us
The socket will (annoyingly) sit in TIME_WAIT for 60 seconds before you can restart Hennepin
*/
function h(){
	// save our data
	su();sw();
	// call it a night
	l("halt");
	die();
}

// s -- search $C for a user named $n, return their socket
function s($n){
	global$C;

	// search all connections, checking for a matching name
	foreach($C as$s=>$v)
		if($v[name]==$n)
			return $C[$s][s];
	// if no connection is found, return false
	return 0;
}

// f -- serialize second arg and save it to a file
function f($f,$a){
	global$D;
	$r=fopen("$D/$f",'w');	// shorter to put the slash here, once
	fwrite($r,serialize($a));
	fclose($r);
	l("saved $f");

	// If using PHP5, comment out above block and:
	// file_put_contents($f,serialize($a));
}

// i -- input a file, unserialize it, and return it
function i($f){
	if(!$d=unserialize(file_get_contents($f)))$d=array();
	return$d;
}

/* r -- registers a verb
 $a is an array holding info on it: first line is name, rest is code
 you may notice in verbs that the second line is '// <?': this is just for vim highlighting
 bytesaving: all verbs get started with 'global$C,$U,$V,$W;'
 all verbs get called with:
	$s -- socket of player
	$d -- whether this is a delayed call
	$r -- rest of input after command
*/
function r($a){
	global$V;

	// create the function for each of its possible names
	// if $a came directly out of a file (eg, on startup) there'll be a \n to trim off $a[0]
	$n=explode(',',trim(array_shift($a)));

	foreach($n as$i)
		$V[$i]=create_function('$s,$d,$r','global$C,$U,$V,$W;'.join($a,"\n"));
}

// lt -- long time, because time() returns only seconds
// it returns a string with lots of precision, the change to float will lose
// all but hundreths
function lt(){
	$t=explode(' ',microtime());
	// PHP floats are only so long -- this gives us two more digits
	// after the decimal
	return$t[1]-pow(10,9).substr($t[0],1);
}

/* t -- registers a tick call
 $f -- name of function to call -- if it starts with $, that verb will be called
 $a -- argument to pass: Can't pass more than one, but $a can be an array
 $w -- how many seconds to call it in
 $s -- optional, the user's socket for delayed verb calls
*/
function t($f,$a,$w,$s=0){
	global$T;

	$T[]=array(lt()+$w,$f,$a,$s);	// fewer bytes vs. proper subscript labels
}

// dt -- do ticks that are due or overdue
function dt(){
	global$T,$V;

	// go through all the pending ticks
	foreach($T as $e=>$f)
		if($f[0]<=lt()){				// if this entry needs to be done
			if($f[1][0]=='$')
				$V[substr($f[1],1)]($f[3],1,$f[2]);	// call the verb
			else
				$f[1]($f[2]);			// do it
			unset($T[$e]);				// and call it done
		}
}

// su -- save users, if $t, we were called by tick, so set up the next one
function su($t=0){
	global$U;
	f('users',$U);		// just farm it out to the right function

	if($t)t('su',1,300);
}

// sw -- save world; if $t, we were called by tick, so set up the next one
function sw($t=0){
	global$W;
	f('world',$W);		// just farm it out to the right function

	if($t)t('sw',1,300);
}

// gn -- generate a network recursively
function gn($n){
	global $W;

	// do
}
			/* $W -- the world data
			first-level index is network id, which has these keys:

			* [name] -- name of network owner
			* [n] -- array of the nodes, indexed by [name]
				* [type] -- the type of node
				* [vit] -- vitality of the node
				* [hp] -- hp of the node
			* [c] -- array of connections between nodes, indexed by [name]
				* [f] -- node id from
				* [t] -- node id to
				* [bi] -- boolean for bidirectionality
				* [ste] -- stealth of the node
			*/

// Input-handling functions /////////////////////////////////////////

/* every input-handling function gets one line at a time and has two args:
 $s for what connection in $C it came from
 $m is the trim()ed line of input (so no leading/trailing whitespace/newlines)
*/

// get their name
function name($s,$m){
	global$C,$U;
	$m=strtolower($m);

	// pass off new users to newu()
	if($m=='new'){w($s,"Desired name:");$C[$s][in]='newu';return;}

	if($U[$m]&&!$C[s($m)]) {
		unset($U[$m][s],$U[$m][ip]);
		$C[$s]=array_merge($C[$s],$U[$m]);	// load their info
		w($s,"Password:");
		$C[$s][in]='pwd';			// pass control to pwd()
	}else
		w($s,"Bad name, try again:");
}

// new user, get and save their name
function newu($s,$m){
	global$C,$U;

	// only alphabetic names allowed
	$m=preg_replace('[^a-z]','',strtolower($m));

	// no blank or existing names allowed
	if(!$m||$U[$m]){w($s,"Bad name, try again:");return;}

	$C[$s][name]=$m;					// save their name
	$C[$s][l]="A plain user.";				// give them a generic description
	//$c[$s][c]=0;						// default ansi off
	$C[$s][cash]=0;						// start with $0
	w($s,"Password:");
	$C[$s][in]='pwd';					// get their password

	// if there are no users saved yet, we've just started up
	// so lucky user #1 gets to be wiz
	if(!$u)$C[$s][wiz]=1;
}

// check/set password
function pwd($s,$m){
	global$C,$U;

	if($m==$C[$s][pwd]||					// can login with a matching pwd
	  ($C[$s][pwd]==""&&$m&&$C[$s][pwd]=$m)){		// if blank pwd space, save new user password
		a("~12{$C[$s][name]} logs into Hennepin");		// tell everyone
		l("login: {$C[$s][ip]} {$C[$s][name]}");	// log it
		$C[$s][a]='root';				// send them home
		$C[$s][r]='root';
		// default stats
		$C[$s][vit]=4;
		$C[$s][ste]=4;
		$C[$s][str]=5;
		$C[$s][per]=5;
		$C[$s][pool]=3;
		$C[$s][hp]=10;
		w($s,"Type 'commands'");			// give instructions
		$C[$s][in]='game';				// pass control to game()
		$U[$C[$s][name]]=$C[$s];			// save their character to $U
	}elseif($m)
		w($s,"Bad password, try again:");		// yeah, I'm way too nice
}

// main game input function
function game($s,$m){
	global$C,$V,$U,$T,$W;			// $U,$T are here for the benefit of eval()d code

	// log the input
	l($C[$s][name].": $m");

	// parse $m into command $o and rest $r
	if($pos=strpos($m,' ')){
		$o=substr($m,0,$pos);
		$r=substr($m,$pos+1);

	// handle the case with no arguments, leave $r unset
	}else
		$o=$m;

	// If they're a wizard, they can eval() -- execute raw PHP. Powerful stuff.
	if($o=='ev'&&$C[$s][wiz]==1)
		eval("$r;");			// I always forget the ;, it's worth 3 bytes

	// if they called a verb by full name
	elseif($V[$o])
		$V[$o]($s,0,$r);		// all verbs get these args

	// or if they did an abbreviation like ' for say
	elseif($V[substr($o,0,1)]&&!in_array(substr($o,0,1),range('a','z')))
		$V[substr($o,0,1)]($s,0,substr($m,1));

	// if they're typing the name of an exit, pass off to go
	elseif(gnc($C[$s][a],$C[$s][r],$o))
		$V["go"]($s,0,$o);

	// just ignore blank entries
	elseif(!$m){}

	// if we don't know *what* they want, react intelligently...
	else
		w($s,"Huh?");

	// set the last command -- is down here so that the ! command works
	if($m&&$C[$s])$C[$s][l]=$m;

	// Give them a prompt with area and room if they're still here
	if($C[$s])socket_write($C[$s][s],"{$C[$s][a]}/{$C[$s][r]} {$C[$s][hp]}> ");
}

// Gameplay functions //////////////////////////////////////

/* gc -- get all connections from the given area/room
*/
function gc($a,$r){
	global$W;
	$v=array();	// conns to return -- the spam is very painful without this

	foreach($W[$a][c] as$n=>$c)
		if($c[f]==$r||$c[t]==$r&&$c[bi])
			$v[$n]=$c;

	return$v;
}

/* gnc -- get named connection for the given area/room
*/
function gnc($a,$r,$n){
	$a=gc($a,$r);
	return$a[$n];
}

/* rd -- roll $n d6
*/
function rd($n){
	while($n--)$a[]=rand(1,6);
	rsort($a);	// prep for cr()
	return$a;
}

/* cr -- compare a rolls to d rolls for success
   Positive numbers are in favor of attacker, negative in favor of defender, 0 is tied
*/
function cr($a,$d){
	if($a<1)return$d*-1;
	if($d<1)return$a;

	// roll the dice
	$a=rd($a);$d=rd($d);

	// cut down size of attacker if defender is smaller
	if(count($a)>count($d))
		$a=array_slice($a,0,count($d));

	// loop and count successes
	$c=0;
	foreach($a as$i=>$v)
		if($v>$d[$i])
			$c++;
		else
			$c--;

	return$c;
}

// Startup /////////////////////////////////////////////////

// By default, PHP stops scripts after 30 seconds
set_time_limit(0);

// Turn off PHP's output buffering
while(@ob_end_flush());

// create the resource socket -- socket_create_listen saves so many bytes!
// PHP's default error messages are informative enough if something breaks here
$RS=socket_create_listen($P)or die();
socket_set_nonblock($RS)or die();

// start saving users and world every 5 minutes
su(1);
sw(1);

// load up all the saved verbs and register them
// When . and .. are read, file() returns an empty array; r() will be OK
$r=opendir("$D/verbs");
while($f=readdir($r))	// no check for !== false, no dir in /verbs is named null, 0, or false
	if(substr($f,-4)==".php")
		r(file("$D/verbs/".$f));
ksort($V);		// not needed, but makes for nicer display from 'commands'
// PHP will close the dir for us when we exit, amount of ram/handles wasted are trivial
//closedir($r);

// Game Loop ///////////////////////////////////////////////

// We'll die() when done and cleaned up (see h()), so this loop is infinite
while(1){

// check for activity, with timeout (1/10th of a second) to deal with game tasks
$r=array($RS);					// $rs is included to listen for new connections
foreach($C as$p){array_push($r,$p[s]);}		// and load all the players' sockets
$a=socket_select($r,$w=NULL,$e=NULL,0,100000);	// $a is the number of sockets to pay attention to

// Deal with socket errors by dumping them to console, and soldiering on
if(false===$a)l(socket_strerror(socket_last_error()));

// deal with any network happenings
if($a>0){
	
	// accept new connections / read incoming data
	foreach($r as$s) {
		// if it's a new connection, and we accept it, save it
		if($s==$RS&&$n=socket_accept($RS)){
			socket_getpeername($n,$C[$n][ip]);	// get their IP
			$C[$n][s]=$n;				// resource ID can't be a key
			$C[$n][in]='name';			// name() starts the login process
			w($n, "Welcome to Hennepin, enter your name or: new");
			l("conn: {$C[$n][ip]}");

		}else{ // input to take
			if(!$i=socket_read($s,99))		// lost connection
				d($s);
			else					// add it to their input buffer
				$C[$s][ib].=$i;
		}
	}

	// maybe do output buffering with $w here?
	// it's not likely to be a problem, so I'll save the bytes
}

// game functioning

// process input -- loop over all players and deal with their buffers
foreach($C as$s=>$p){
	if(!$p[ib])continue;					// nothing to do

	$p[ib]=str_replace("\r",'',$p[ib]);			// no \r's! rar!

	// if there's a whole line of input, deal with it
	if(!$p[b]&&false!==$pos=strpos($p[ib],"\n")){
		$p[in]($p[s],trim(substr($p[ib],0,$pos)));	// send to the func. that gets their input
		if($C[$s])$C[$s][ib]=substr($C[$s][ib],$pos+2);	// if they're still here, trim the buffer -- use $C so the ! command can dick with the buffer
	}
}

// time to process any pending ticks
dt();

}	// end of the game's infinite while() loop
?>
