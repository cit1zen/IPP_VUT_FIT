#!/usr/bin/php
<?php
#DKA:xorman00

# AUTOMAT
$states=array();
$rules=array();
$alphabet=array();
$bg_state;
$end_states=array();
# EPSILON
$EPSILON ='';

# "Struktura" popisujuca prechodove pravidlo automatu
class Pravidlo{
	public $bg_state;
	public $symbol;
	public $end_state;
	function __construct($bg_state,$symbol,$end_state) 
	{
		$this->bg_state=$bg_state;
		$this->symbol=$symbol;
		$this->end_state=$end_state;
	}
}



# Pri determinizacii, odstranovani epsilon prechodov je dany stav definovany polom podstavov
# tato funkcia konvertuje pole na string ktory vyjadruje stav 
function from_array_to_stav($vstupne_pole_stavov)
{	
	if(is_array($vstupne_pole_stavov))
	{
		natsort($vstupne_pole_stavov);
		if(count($vstupne_pole_stavov)>1)
			return implode ( "_",(array)$vstupne_pole_stavov);
		else
			return implode("_",(array)$vstupne_pole_stavov);		
	}
	else
		return $vstupne_pole_stavov;
}



# Porovnavacia funkcia pre pravidla, aby sme mohli pravidla zoradit
function compare_pravidlo($a,$b)
{
	$poradie=strcmp ( $a->bg_state , $b->bg_state );
	if ($poradie==0)
	{
		$poradie=strcmp (  $a->symbol , $b->symbol );
		if ($poradie==0)
		{
			$poradie=strcmp ( $a->end_state , $b->end_state );
		}
	}
	return $poradie;
}




#===== CLI PARS =====

#--help
$help=False;
#--input=filename
$input=False;
#--output=filename
$output=False;
#--no-epsilon-rules,-e
$no_epsilon=False;
#-d, --determinization
$determinization=False;
#-i, --case-insensitive
$case_insensitive=False;

# Nacitanie argumentov CLI
$shortopts  = "e";
$shortopts .= "d";
$shortopts .= "i";
$longopts  = array(
    "input:","output:","help","no-epsilon-rules","determinization","case_insensitive"         
);
$arguments = getopt($shortopts, $longopts);

# Spracovanie argumentov
foreach($arguments as $argument_name => $argument_value) {
	# Nastavovanie 
    switch ($argument_name) {
    	case 'input':
    		$input=$argument_value;
    		break;
      	case 'output':
    		$output=$argument_value;
    		break;  	
    	case 'help':
    		$help=True;
    		break;
       	case 'no-epsilon-rules':
       		$no_epsilon=True;
    		break;
    	case 'e':
    		$no_epsilon=True;
    		break;
    	case 'determinization':
    		$determinization=True;
    		break;
    	case 'd':
    		$determinization=True;
    		break;
    	case 'case_insensitive':
    		$case_insensitive=True;
    		break;
    	case 'i':
    		$case_insensitive=True;
    		break;
    }
}


# Ci je potrebne vypisat help
if($help)
{
	echo "HELP skriptu DKA:\n\n";
	echo "--help\n    vypise tuto napovedu\n";
	echo "--input=filename\n    subor s KA\n";
	echo "--output=filename\n    subor do ktoreho zapiseme vysledky KA\n";
	echo "-e, --no-epsilon-rules\n    vytvorenie KA bez epsilon prechodov, neda sa kombinovat s -d\n";
	echo "-d, --determinization\n    determinizacia KA, neda sa kombinovat s -e\n";
	echo "-i, --case-insensitive\n    nebude brany ohlad na velke a male pismena\n";
	exit(0);
}

# Ak by bola zapata aj determinizacia aj odstranenie epsilon prechodov
# tak je to chyba
if(($determinization && $no_epsilon))
{
	fwrite(STDERR, "-d aj -e\n");
	exit(1);
}

#===== CLI PARS =====








#===== VSTUPNY AUTOMAT =====
# Kontrola predpisu KA
# ulozenie stavov, abecedy, pravidiel, ... do prislusnych zoznamov pre dalsie spracovanie

# Vstupny string obsahujuci automat
$automat_string=" ";

# Nacitame data zo suboru/streamu
if($input!=False)
{
	$automat_string=file_get_contents($input) or exit(2);
}
else
{
	$automat_string=file_get_contents('php://stdin') or exit(2);
}

# Ak je zapnuty prepinac --case-insensivity
# aby sme sa o to nemuseli uz zaoberať
if($case_insensitive)
{
	$automat_string=mb_strtolower($automat_string,"UTF-8");
}

# Odstranovanie komentarov a odriadkovania
$automat_string = ereg_replace("'\n'","'<>'",$automat_string);
$automat_string = ereg_replace("#[^'][^\n]+","",$automat_string);
$automat_string = str_replace(array("\r", "\n"), '', $automat_string);
$automat_string = ereg_replace("'<>'","'\n'",$automat_string);

# Kontrola ^(AUTOMAT)$
if(!ereg("^[ \t]*\(.+\)[ \t]*$",$automat_string ))
{
	fwrite(STDERR, "^(AUTOMAT)$\n");
	exit(40);
}
else
# ^AUTOMAT$
{
	$automat_string = ereg_replace("(^[ \t]*\()|(\)[ \t]*$)","",$automat_string);
}

# Odstranenie medzier a tabulatorov
$automat_string = ereg_replace(" ","<>",$automat_string);
$automat_string = ereg_replace("\t","<-",$automat_string);
$automat_string = ereg_replace("'<>'","' '",$automat_string);
$automat_string = ereg_replace("'<-'","'\t'",$automat_string);
$automat_string = ereg_replace("<>","",$automat_string);
$automat_string = ereg_replace("<-","",$automat_string);

# STAVY|ABECEDA|PRAVIDLA|POCIATOCNY_STAV|KONCOVE STAVY|
$automat_string = ereg_replace("(^{)|(}$)","",$automat_string);
$automat_string = ereg_replace("(}[ \t]*,)|(,[ \t]*{)","|",$automat_string);
$automat_string = ereg_replace("(\|[ \t]*\{)","|",$automat_string);
$automat_split = split("\|", $automat_string);

# STAVY
$states_preparsed=split(",", $automat_split[0]);
# Kontrola spravnosti predpisu stavu 
# ulozenie stavu do zoznamu stavov
for($i=0;$i<count($states_preparsed);$i++)
{
	if(ereg("^([a-zA-Z])|([a-zA-Z][a-zA-Z0-9_-]*[a-zA-Z0-9]+)$",$states_preparsed[$i]))
	{
		# Ak tam uz ten stav nahodou neni
		if(!(in_array($states_preparsed[$i], $states)))
		{
			$states[]=$states_preparsed[$i];
		}

	}
	else
	{	
		fwrite(STDERR, "STAV\n");
		exit(40);
	}
}
# Zoradenie
natsort($states);

# ABECEDA
# Ciarku budeme chcet pouzit ako oddelovac, preto ked je ako znak vstupnej abecedy tak ju zmenime
$alphabet_preparsed =preg_replace("/,',',/u","/,'<>',/u",$automat_split[1]);
# Oddelime si jednotlive znaky
$alphabet_preparsed=split(",", $alphabet_preparsed);
# Pridanie epsilon pravidla
$alphabet_preparsed[]="";
for($i=0;$i<count($alphabet_preparsed);$i++)
{
	# Zbavime sa apostrofov
	$alphabet_preparsed[$i] =preg_replace("/(^')|('$)/u","",$alphabet_preparsed[$i]);
	# Ciarka
	if(preg_match("/^<>$/u",$alphabet_preparsed[$i]))
	{
		if(!(in_array(",", $alphabet)))
			$alphabet[]=",";
	}
	# Epsilon
	else if (preg_match("/^$/u",$alphabet_preparsed[$i])) {
		if(!(in_array("", $alphabet)))
			$alphabet[]=$EPSILON;
	}
	# Apostrof
	else if (preg_match("/^''$/u",$alphabet_preparsed[$i])) {
		if(!(in_array("'", $alphabet)))
			$alphabet[]="'";
	}
	# Vsetky ostatne znaky
	else if(preg_match("/^.$/u",$alphabet_preparsed[$i]))
	{
		if(!(in_array($alphabet_preparsed[$i], $alphabet)))
		{
			$alphabet[]=$alphabet_preparsed[$i];
		}
	}
	else
	{
		fwrite(STDERR, "ABECEDA\n");
		exit(40);
	}
}
# Zoradenie
natsort($alphabet);
# Vstupna abeceda prazdna
if(count($alphabet)<=1)
	exit(41);



# PRAVIDLA
# Ak je ciarka vstupnym znakom
$rules_preparsed =ereg_replace("','","'<>'",$automat_split[2]);
$rules_preparsed =split(",", $rules_preparsed);
for($i=0;$i<count($rules_preparsed);$i++)
{
	# Ciarku vratime naspat
	$rules_preparsed[$i] =ereg_replace("'<>'","','",$rules_preparsed[$i]);
	# Kontrola pravidla a jeho ulozenie do zoznamu pravidiel
	if(preg_match("/^[0-9a-zA-Z_]+('.?')|('''')->[0-9a-zA-Z_]+$/u",$rules_preparsed[$i]))
	{
		# Rozdelenie pravidla na bg_state 'symbol' -> koncovy_stav
		preg_match_all("/^[0-9a-zA-Z_]+/u",$rules_preparsed[$i],$state1);
		preg_match_all("/'.?'/u",$rules_preparsed[$i],$symbol);
		preg_match_all("/[0-9a-zA-Z_]+$/u",$rules_preparsed[$i],$state2);
		$proto_rule=array();
		$proto_rule[]=$state1[0][0];
		$proto_rule[]=$symbol[0][0];
		$proto_rule[]=$state2[0][0];
		$proto_rule[1]=preg_replace("/^'/u","",$proto_rule[1]); 
		$proto_rule[1]=preg_replace("/'$/u","",$proto_rule[1]);

		# Kontrola jednotlivych casti pravidla
		if (!(in_array($proto_rule[0],$states))) 
		{
			fwrite(STDERR, "Pravidlo, stav1\n");
			exit(41);
		}
		if (!(in_array($proto_rule[1],$alphabet))) 
		{
			fwrite(STDERR, "Pravidlo, abeceda\n");
			exit(41);
		}
		if (!(in_array($proto_rule[2],$states))) 
		{
			fwrite(STDERR, "Pravidlo, stav2\n");
			exit(41);
		}	
		# Ulozenie pravidla 
		$rules[]=new Pravidlo($proto_rule[0],$proto_rule[1],$proto_rule[2]);		
	}
	# Nie su ziadne pravidla, co nemusi byt chyba
	else if(ereg("^$",$rules_preparsed[0]) && count($rules_preparsed)==1)
		;
	else
	{
		fwrite(STDERR, "Pravidlo\n");
		exit(40);
	}
}


# ZACIATOCNY STAV
# Nacitanie a kontrola zaciatocneho stavy
if (ereg("^[0-9a-zA-Z_]+$",$automat_split[3],$bg_preparsed))
{
	if(!(in_array($bg_preparsed[0],$states)))
	{
		fwrite(STDERR, "Vstupny, stav\n");
		exit(41);
	}
	else
	{
		$bg_state=$bg_preparsed[0];
	}
}
else
{
	fwrite(STDERR, "Vstupny\n");
	exit(40);
}

# KONCOVE STAVY
# Nacitanie a kontrola vsetkych koncovych stavov
if (preg_match_all("/[0-9a-zA-Z_]+/u",$automat_split[4],$end_states_preparsed))
{
	$end_states_preparsed=$end_states_preparsed[0];
	for($i=0;$i<count($end_states_preparsed);$i++)
	{
		if(!(in_array($end_states_preparsed[$i],$states)))
		{
			fwrite(STDERR, "Koncovy, stav\n");
			exit(41);
		}
		else
		{
			$end_states[]=$end_states_preparsed[$i];
		}
	}
}
else if (ereg("^$",$automat_split[4],$end_states_preparsed))
{
	;
}
else
{
	fwrite(STDERR, "Koncovy\n");
	exit(40);
}
# Zoradenie
natsort($end_states);

#===== VSTUPNY AUTOMAT =====






#===== KA BEZ EPSOLON =====
# Odstranenie epsilon prechodov

if($no_epsilon || $determinization)
{
	# Pole do ktoreho ukladame nove pravidla
	$new_rules=array();
	# Aby sme presli pravidla pre vsetky stavy
	for($i=0;$i<count($states);$i++)
	{
		# Stavy ktore sme uz presli
		$processed_states=array();
		# Stavy ktore este musime prejst
		$states_to_go=array();
		$states_to_go[]=$states[$i];
		$index_states_to_go=0;
		while(count($states_to_go)!=$index_states_to_go)
		{
			for($index=0;$index<count($rules);$index++)
			{
				# Zaujimame sa len o pravidla ktore tvoria epsilon uzaver
				if($states_to_go[$index_states_to_go]==$rules[$index]->bg_state)
				{
					# Je to epsilon pravidlo
					# Vytvarani epsilon uzaveru
					if($rules[$index]->symbol=="")
					{
						if(!(in_array($rules[$index]->end_state,$processed_states)))
							if(!(in_array($rules[$index]->end_state,$states_to_go)))
								$states_to_go[]=$rules[$index]->end_state;
					}
					# Nie je to epsilon pravidlo
					else
					{
						$new_rules[]=new Pravidlo($states[$i],$rules[$index]->symbol,$rules[$index]->end_state);
					}
				}
			}
			$index_states_to_go+=1;
		}
	}
	$rules=$new_rules;
	# Odstanenie epsilon z abecedy
	#$alphabet = array_filter($alphabet);
	foreach ($alphabet as $key=>$value)
		if ($value=="")
			unset($alphabet[$key]);

	#$alphabet=array_diff($alphabet, $epsilon_array);
	$alphabet=array_values($alphabet);
}
#===== KA BEZ EPSOLON =====





#===== KA DO DKA =====
# Z automatu bez epsilon prechodov spravime DKA

if($determinization)
{
	# Novy automat
	$new_states=array();
	$new_rules=array();
	$new_end_states=array();
	# V algoritme je to Q new
	# Pracujeme z tym ako z heapom
	$heap_states=array();
	$heap_states[]=$bg_state;

	# Determinizacia
	while(count($heap_states))
	{
		# Zapiseme si stav ktory budeme prehladavat
		$processed_state=array();
		$processed_state[]=$heap_states[0];

		# Posuneme sa v heape
		unset($heap_states[0]);
		$heap_states=array_values($heap_states);

		if(is_array($processed_state[0]))
			$processed_state=$processed_state[0];
		$processed_state_string=from_array_to_stav($processed_state);

		# Ak sme dany stav uz prehladali
		if(in_array($processed_state_string,$new_states))
		{
			continue;
		}

		if(!in_array($processed_state_string, $new_states) )
		{
			$new_states[]=$processed_state_string;
		}

		# Prejdenie abecedy
		for($i=0;$i<count($alphabet);$i++)
		{
			# Najdeme zdruzeny stav, ktory nam pokryje dany znak
			$out_state=array();
			for($rule_index=0;$rule_index<count($rules);$rule_index++)
			{
				if(in_array($rules[$rule_index]->bg_state, $processed_state) && $rules[$rule_index]->symbol==$alphabet[$i])
					if(!(in_array($rules[$rule_index]->end_state, $out_state)))
						$out_state[]=$rules[$rule_index]->end_state;
			}
			# Ak pre dany symbol abecedy bol najdeny vystupny stav tak ho zapiseme pre dalsie spracovanie
			# a vytvorime nove pravidlo
			if(count($out_state))
			{	
				$out_state_string=from_array_to_stav($out_state);		
				$new_rules[]=new Pravidlo($processed_state_string,$alphabet[$i],$out_state_string);

				if(!(in_array($out_state_string, $new_states)))
				{
					$heap_states[]=$out_state;
				}
			}
		}
		# Overime ci sme nevytvorili novy koncovy stav
		for($i=0;$i<count($processed_state);$i++)
		{
			if(in_array($processed_state[$i], $end_states))
			{
				$processed_state_string=from_array_to_stav($processed_state);
				$new_end_states[]=$processed_state_string;
				# Ak tento stav nie je zapisany, co sa stane ak z neho nic nevychadza tak ho zapiseme
				if(!in_array($processed_state_string, $new_states) )
					$new_states[]=$processed_state_string;
			}
		}
	}
	# Stary automat prepiseme novym
	$states=$new_states;
	$rules=$new_rules;
	$end_states=$new_end_states;

	# Zoradenie
	natsort($states);
	natsort($end_states);
}
#===== KA DO DKA =====





#===== VYPIS =====

# Otvorenie suboru
if($output!=False)
{
	$output = fopen($output, "w") or exit(3);
}
else
{
	$output = fopen('php://output', 'w') or exit(3);	
}

#(
fputs($output, "(\n"); 
#{STAVY},
fputs($output,"{".implode(", ",$states)."},\n");

#{ABECEDA},
foreach ($alphabet as $key=>$value)
	if ($value=="")
		unset($alphabet[$key]);
$alphabet=array_values($alphabet);
for($i=0;$i<count($alphabet);$i++)
{
	# Apostrofova vynimka
	if($alphabet[$i]=="'")
		$alphabet[$i]="''''";
	else
		$alphabet[$i]="'".$alphabet[$i]."'";
}
fputs($output,"{".implode(", ",$alphabet)."},\n");

#PRAVIDLA
fputs($output, "{\n");
usort($rules, "compare_pravidlo");
for($i=0;$i<count($rules);$i++)
{
	# ''' vynimka
	if($rules[$i]->symbol=="'")
		$rules[$i]->symbol="''";
	fputs($output,$rules[$i]->bg_state." '".$rules[$i]->symbol."' -> ".$rules[$i]->end_state);
	if($i!=count($rules)-1)
		fputs($output,",\n");
	else
		fputs($output,"\n");
}
fputs($output, "},\n"); 

# Zaciatocny stav
fputs($output,$bg_state.",\n");

# Koncove stavy
fputs($output,"{".implode(", ",$end_states)."}\n");
#)
fputs($output, ")");
fclose($output); 
#===== VYPIS =====
?>