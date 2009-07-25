go
// <?
if($d){
	$C[$s][b]=0;
	// if the connection (exit) exists
	if($e=gnc($C[$s][a],$C[$s][r],$r)){
		b($s,"~11{$C[$s][name]} ~12leaves: ~03$r");
		w($s,"~12You leave: $r");
		// handle bidirection exists
		if($C[$s][r]==$e[t])
			$C[$s][r]=$e[f];
		else
			$C[$s][r]=$e[t];
		b($s,"~11{$C[$s][name]} ~12arrives");
	}else
		w($s,"~12no such connection");
}else{
	$C[$s][b]=1;
	t('$go',$r,6,$s);
	w($s,"~12going...");
}
