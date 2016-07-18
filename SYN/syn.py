#!/usr/bin/python3
# -*- coding: utf-8 -*-
#SYN:xorman00

import re
re.UNICODE
from argparse import ArgumentParser 
import sys

#===================================================================================================================
#												HELP
#===================================================================================================================

help_string="""HELP ku skriptu SYN:

--help                  vypise napovedu

--format=filename       adresa suboru z formatom

--input=filename        subor zo ktoreho text mame zvyraznit
                        default = stdin

--output=filename       subor do ktoreho zapisujeme zvyrazneni text
                        default = stdout

--br                    prida tag <br /> na koniec riadku

"""

#===================================================================================================================
#												MAIN
#===================================================================================================================

def main():

	# Pole regexov 
	regex_pole=[]


	# ARGUMENTY CLI
	parser=MyArgumentParser()
	parser.add_argument('--format', nargs=1)
	parser.add_argument('--input',default="", nargs=1)
	parser.add_argument('--output',default="", nargs=1)
	parser.add_argument('--br', default=False,action='store_true')
	parser.parse_args(namespace=parser)

	try:
		FORMAT_SUBOR=''.join(parser.format)
	except:
		FORMAT_SUBOR=False

	try:
		INPUT_SUBOR=''.join(parser.input)
	except:
		INPUT_SUBOR=False

	try:
		OUTPUT_SUBOR=''.join(parser.output)
	except:
		OUTPUT_SUBOR=False

		
	BREAK=parser.br


	# FORMATOVACI SUBOR
	try:
		if(FORMAT_SUBOR):
			# Otvorenie
			FORMAT_SUBOR=open(FORMAT_SUBOR,"r")

			# Nacitanie
			for riadok in FORMAT_SUBOR:
				# Ziskanie dat
				
				regularny_vyraz=re.match("^[^\t]+",riadok)
				regularny_vyraz=regularny_vyraz.group(0)
				formaty=re.search("\t.+",riadok)
				formaty=formaty.group(0).rstrip("\t ")
				for format in re.split(",",formaty):
					regex_pole.append(formatovac(regularny_vyraz,format))
			
			FORMAT_SUBOR.close()

			# Ak bol formatovaci subor prazdny tak ukoncime progrm
			if(len(regex_pole)==0):
				raise IOError

	except IOError:
		FORMAT_SUBOR=False
	# Chyba vo formatovacom subore
	except TypeError:
		exit(4)


	# FORMATOVANIE
	text=""
	formatovacia_mapa=[]
	try:
		# NACITANIE VSTUPNEHO SUBORU
		if(INPUT_SUBOR):			
			# Nacitanie riadkov
			with open(INPUT_SUBOR,"r") as INPUT_SUBOR:
				for riadok in INPUT_SUBOR:
					text=text+riadok
		else:
			for riadok in sys.stdin:
				text=text+riadok
		
		for i in range((len(text)+1)):
			formatovacia_mapa.append([])

		# OTAGOVANIE TEXTU
		if(FORMAT_SUBOR):
			# Aplikovanie regexov
			for regularny_vyraz in regex_pole:
				regularny_vyraz.otaguj(text,formatovacia_mapa)

			# Zapisanie tagov do textu
			medzi_string=""
			for index_v_riadku in range(len(text)+1):
				# Zoradenie, ukoncujuce dopredu, zaciatocne dozadu
				ukoncujuce_tagy=[]
				zaciatocne_tagy=[]
				for tag in formatovacia_mapa[index_v_riadku]:
					if tag!=[]:
						# Ukoncujuci tag
						if(re.match("</",tag)):
							ukoncujuce_tagy.append(tag)
						else:
							zaciatocne_tagy.append(tag)

				# Najprv zoradime ukoncujuce tagy a tie potom vypiseme
				# Prvy znak nema pravdepodobne ziadne
				ukoncujuce_tagy=spravne_poradie_tagov(formatovacia_mapa,index_v_riadku,ukoncujuce_tagy)
				for tag in ukoncujuce_tagy:
					if tag!=[]:
						medzi_string=medzi_string+str(tag)

				# Zaciatocne uz su v spravnom poradi, tak ich len vypiseme
				# za koncove tagy
				for tag in zaciatocne_tagy:
					if tag!=[]:
						#formatovacia_mapa[index_v_riadku].append(tag)
						medzi_string=medzi_string+str(tag)
					
				"""
				# Zapisovanie tagov do textu
				for tag in formatovacia_mapa[index][index_v_riadku]:
					if tag!=[]:
						medzi_string=medzi_string+str(tag)
				"""

				# Pridanie znaku za vsetky tagy
				if(index_v_riadku<len(text)):
					medzi_string=medzi_string+str(text[index_v_riadku])
			text=medzi_string


	except IOError:
		print("INPUT", file=sys.stderr)
		exit(2)
	#except Exception as error:
		#print(error, file=sys.stderr)
		#exit(2)


	# VYPIS
	try:
		if(OUTPUT_SUBOR):
			with open(OUTPUT_SUBOR,"w") as OUTPUT_SUBOR:
				if(BREAK):
					text=re.sub("\n","<br />\n",text)
					OUTPUT_SUBOR.write(text)
				else:
					OUTPUT_SUBOR.write(text)
		else:
			if(BREAK):
				text=re.sub("\n","<br />\n",text)
				sys.stdout.write(text)
			else:
				sys.stdout.write(text)

					

	except IOError:
		print("OUTPUT", file=sys.stderr)
		exit(3)

	exit(0);


#===================================================================================================================
#												FUNKCIE
#===================================================================================================================

# Podla koncoveho tagu priradi zaciatocny
# dolezite pre zoradenie koncovych tagov
def spravne_poradie_tagov(formatovacia_mapa,spracovavany_index,list_koncovych_tagov):
	zoradene_tagy=[]
	pocet_nezoradenych_tagov=len(list_koncovych_tagov)
	# Vraciame sa naspat vo formatovacej mape, aby sme urcili poradie koncovych tagov
	for pozicia in reversed(formatovacia_mapa[:spracovavany_index]):
		if(pocet_nezoradenych_tagov):
			# Musime obratit poradie, lebo na konci tagy su blizsie ako na tagy na zaciatku
			for tag_na_pozicii in reversed(pozicia):
				for koncovy_tag in list_koncovych_tagov:
					# Ak tag na tejto pozicii zodpoveda nejakemu nezoradenemu koncovemu tagu
					if zaciatocny_ku_koncovemu(tag_na_pozicii,koncovy_tag):
						zoradene_tagy.append(koncovy_tag)
						pocet_nezoradenych_tagov-=1
		else:
			break
	return zoradene_tagy

# Zisti ci dany tag je zaciatocny tak koncoveho tagu
def zaciatocny_ku_koncovemu(tag,koncovy_tag):
	if(koncovy_tag=="</i>"):
		if(tag=="<i>"):
			return True
	if(koncovy_tag=="</b>"):
		if(tag=="<b>"):
			return True
	if(koncovy_tag=="</u>"):
		if(tag=="<u>"):
			return True
	if(koncovy_tag=="</tt>"):
		if(tag=="<tt>"):
			return True
	if(koncovy_tag=="</font>"):
		if(re.match("<font",tag)):
			return True
	else:
		return False



#===================================================================================================================
#												TRIEDY
#===================================================================================================================


# Trieda ktora sa stara o formatovanie textu
class formatovac:

	# Konstruktor
	def __init__(self,proto_regex,proto_typ):
		regularny_vyraz=self.regex(proto_regex)
		self.regularny_vyraz=re.compile(regularny_vyraz,re.UNICODE)
		self.beg_tag=self.tag(True,proto_typ)
		self.end_tag=self.tag(False,proto_typ)

	def regex(self,proto_regex):
		regularny_vyraz=str(proto_regex)

		# %%
		regularny_vyraz=re.sub("%%","~",regularny_vyraz)

		# .. CHYBA a odstranenie .
		if(re.match("[^%]\.\.",regularny_vyraz)):
			raise TypeError
		else:
			try:
				for index in ([m.start() for m in re.finditer("(?<!%)\.",regularny_vyraz)]):
					medzi_regularny_vyraz=regularny_vyraz[:index]
					regularny_vyraz=medzi_regularny_vyraz+regularny_vyraz[index+1:]
			except:
				pass

		# Negacia
		regularny_vyraz=re.sub("(?<!%)(!)(([^%])|(%.))","[^\g<2>]",regularny_vyraz)
		regularny_vyraz=re.sub("^(!)(([^%])|(%.))","[^\g<2>]",regularny_vyraz)

		# White space
		regularny_vyraz=re.sub("%s","\\s",regularny_vyraz)

		# Lubovolny znak
		regularny_vyraz=re.sub("%a",".",regularny_vyraz)

		# Cislo
		regularny_vyraz=re.sub("%d","\\d",regularny_vyraz)

		# Male pismena
		regularny_vyraz=re.sub("%l","[a-z]",regularny_vyraz)

		# Velke pismena
		regularny_vyraz=re.sub("%L","[A-Z]",regularny_vyraz)
		
		# Mala aj velka
		regularny_vyraz=re.sub("%w","[a-zA-Z]",regularny_vyraz)

		# Vsetka pismena a cisla
		regularny_vyraz=re.sub("%W","[a-zA-Z0-9]",regularny_vyraz)

		# Tabulator
		regularny_vyraz=re.sub("%t","\t",regularny_vyraz)

		# Newline
		regularny_vyraz=re.sub("%n","\n",regularny_vyraz)

		# Specialny symboli
		regularny_vyraz=re.sub("%!","!",regularny_vyraz)
		regularny_vyraz=re.sub("%(?=[\.\|\*\+\(\)])","\\\\",regularny_vyraz)

		# %%
		regularny_vyraz=re.sub("~","%",regularny_vyraz)

		# CHYBA ZOSTAL NAM !
		#if(re.search("[^\\]!")):
			#raise TypeError

		# Aby neboli prazdne retazce
		if(re.match(regularny_vyraz,"")):
			regularny_vyraz=re.sub("\*","+",regularny_vyraz)
		
		#print(regularny_vyraz+" REG", file=sys.stderr)

		#print(regularny_vyraz)
		return regularny_vyraz

	# Vrati nam zaciatocny a koncovy tag
	def tag(self,beg_tag,proto_typ):
		proto_typ=re.sub('\s+', '', proto_typ)
		if(proto_typ=="italic"):
			if(beg_tag):
				return "<i>"
			else:
				return "</i>"
		if(proto_typ=="bold"):
			if(beg_tag):
				return "<b>"
			else:
				return "</b>"
		if(proto_typ=="underline"):
			if(beg_tag):
				return "<u>"
			else:
				return "</u>"
		if(proto_typ=="teletype"):
			if(beg_tag):
				return "<tt>"
			else:
				return "</tt>"
		if(re.search("^size:[1-7]$",proto_typ)):
			if(beg_tag):
				return re.sub("^size:([1-7])$","<font size=\g<1>>",proto_typ)
			else:
				return "</font>"
		if(re.search("^color:[0-9ABCDEF]{6}$",proto_typ)):
			if(beg_tag):
				return re.sub("^color:([0-9ABCDEF]{6})$","<font color=#\g<1>>",proto_typ)
			else:
				return "</font>"
		else:
			raise TypeError; #return

	# Zistime kde mame umiestnit tagy
	def otaguj(self,text,formatovacia_mapa):
		for index in ([m.start() for m in re.finditer(self.regularny_vyraz,text)]):
			#print(str(index)+" Z", file=sys.stderr)	
			formatovacia_mapa[index].append(self.beg_tag)
		for index in ([m.end() for m in re.finditer(self.regularny_vyraz,text)]):
			#print(str(index)+"   K", file=sys.stderr)
			formatovacia_mapa[index].append(self.end_tag)
		#print(len(text), file=sys.stderr)




# Trieda krora obaluje argparse, aby sa vypisal moj help
class MyArgumentParser(ArgumentParser):

	def print_help(self, file=None):
		print(help_string)
		exit(0)

	def print_usage(self, file=None):
		exit(1)



if __name__ == "__main__":
	main()
