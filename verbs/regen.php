regen,r
// <?
// if we're calling ourself to regen
if($d){
	$C[$s][b]=0;

	// make sure regen's needed
	if($C[$s][hp]>9)
		return w($s,"~12no regen needed");

	// calc the points healed
	$p=cr($C[$s][vit],rand(2,4));
	if($p<1)$p=0;
	$C[$s][hp]+=$p;
	w($s,"~12you regen $p pts");

}else{
	$C[$s][b]=1;
	t('$r','',12,$s);
	w($s,"~12regening...");
}
