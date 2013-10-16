#!/bin/sh

URL=http://www.timacad.ru/2students/Raspisanija/

cd cache
wget -nc -c -nv ${URL}Agro-D-13-14.xls
wget -nc -c -nv ${URL}PAE-D-13-14.xls
wget -nc -c -nv ${URL}SAD-D-13-14.xls
wget -nc -c -nv ${URL}ZOO-D-13-14.xls
wget -nc -c -nv ${URL}Ekon-D-13-14.xls
wget -nc -c -nv ${URL}UchFin-D-13-14.xls
wget -nc -c -nv ${URL}GumPed-D-13-14.xls
wget -nc -c -nv ${URL}TEX-D-13-14.xls
cd ..

php parse.php > timiryazev.json
