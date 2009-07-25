hide,h
// <?
// if we're calling ourself
if($d){
	if($r=='un'){
		$C[$s][ste]-=2;
		$C[$s][h]=0;
		w($s,"~12you're not hidden anymore");
	}else{
		$C[$s][b]=0;
		// give the bonus
		$C[$s][ste]+=2;
		$C[$s][h]=1;
		// set up the bonus removal for 15s
		t('$h','un',15,$s);
		w($s,"~12you're hidden");
	}

// otherwise, prepare
}elseif($C[$s][h])	// multiple hiding is not allowed
	w($s,"~12already hiding");

else{
	$C[$s][b]=1;
	t('$h','',4,$s);
	w($s,"~12hiding...");
}
