defend,d
// <?
// if we're calling ourself
if($d){
	if($r=='un'){
		$C[$s][vit]-=4;
		$C[$s][d]=0;
		w($s,"~12you're not defending anymore");
	}else{
		$C[$s][b]=0;
		// give the bonus
		$C[$s][vit]+=4;
		$C[$s][d]=1;
		// set up the bonus removal for 12s
		t('$d','un',12,$s);
		w($s,"~12you're defending");
	}

// otherwise, prepare
}elseif($C[$s][d])	// multiple defending is not allowed
	w($s,"~12already defending");

else{
	$C[$s][b]=1;
	t('$d','',3.5,$s);
	w($s,"~12defending...");
}
