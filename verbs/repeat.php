!
// <?
if(!$r)
	$r=1;

// just slap it on the end of the input buffer
if(is_int((int)$r)&&$r<11)
	while($r--)
		$C[$s][ib].=$C[$s][l]."\n\n";
else
	w($s,"~12bad repeat count");
