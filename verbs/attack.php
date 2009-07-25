attack,a
// <?
// if we're calling ourself
if($d){
	$C[$s][b]=0;
	
	// make sure the target's still around
	$t=s($r);		// load target
	if($C[$s][a]!=$C[$t][a]||$C[$s][r]!=$C[$t][r])
		return w($s,"~12can't find that target");

	$p=cr($C[$s][str],$C[$t][vit]);
	if($p==0)$p=1;
	if($p<0)$p=0;
	$C[$t][hp]-=$p;
	w($s,"~12you attack for $p pts");
	w($t,"~11{$C[$s][name]} ~06attacks you for $p pts!");

	// check if other players in the room notice
	foreach($C as$l)
		if($l[s]!=$s&&$l[s]!=$t&&$l[a]==$C[$s][a]&&$l[r]==$C[$s][r]&&cr($l[per],1)>0)
			w($t,"~11{$C[$s][name]} ~06attacks ~11{$C[$t][name]}~06 for $p pts");
			
}else{
	$C[$s][b]=1;
	t('$a',$r,3,$s);
	w($s,"~12attacking...");
}
