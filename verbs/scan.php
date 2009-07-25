scan,s,look,l
// <?
if($d){
	$C[$s][b]=0;
	if(!$r){
		w($s,"At ~13{$C[$s][a]}~07/~13{$C[$s][r]}~07, detected:");

		// give list of exits
		// WARN
		//if (is_array(gc($C[$s][a],$C[$s][r])))
		foreach(gc($C[$s][a],$C[$s][r])as$d=>$n){
			$x=cr($C[$s][per],$n[ste]);

			// print the connection if the detect it
			if($x>0)
				$e.=" ~14$d~07";

			// show the type if they're good
			if($x>1)
				if($n[f]==$C[$s][r])
					$e.=":~14{$W[$C[$s][a]][n][$n[t]][type]}";
				else
					$e.=":~14{$W[$C[$s][a]][n][$n[f]][type]}";

			// and the name if they're really good
			if($x>2)
				if($n[f]==$C[$s][r])
					$e.="~07:~14{$n[t]}";
				else
					$e.="~07:~14{$n[f]}";
		}
		w($s,"Connections:$e");

		// give list of players
		foreach($C as $n=>$i)
			if($i[a]==$C[$s][a]&&$i[r]==$C[$s][r]&&$s!=$n){
				$x=cr($C[$s][per],$i[ste]);

				// print the player if they detect them
				if($x>0)
					$l.=" ~11{$i[name]}";

				// and their life if they're really good
				if($x>2)
					$l.="~07:~01{$i[hp]}";
			}
		w($s,"Players    :$l");
	}else{
		w($s,"not yet");
	}
}else{
	$C[$s][b]=1;
	t('$l','',1.5,$s);
	w($s,"~12scanning...");
}
