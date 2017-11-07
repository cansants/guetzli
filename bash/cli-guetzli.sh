#/bin/bash

VERSION="1.0"
BIN="/traspas/guetzli/lib/guetzli"
COMPRESION=86
BKPSUFIX=".bak"
LIST="/tmp/.cli-guetzli.list"



if [ $# -lt 1 ]
then
	echo "Usage : $0 /path/to/folder"
    exit
fi

echo "$(date +"%F %T") Starting folder $1"

## Borramos la lista
rm $LIST
echo "$(date +"%F %T") Delete previous list"

## buscamos las imagenes 
find $1 \( -iname \*.png -o -iname \*.jpg \)  -exec ls {} \; > $LIST


while read img; do

  origen=$img
  backup="$img$BKPSUFIX"

  #echo "$(date +"%F %T") Next file $origen"

  if [ ! -f "${backup}" ]; then
  
    # creamos backup
    cp "${origen}" "${backup}"
    echo "$(date +"%F %T") Backup created $backup"
    
    ## Iniciamos la compresion
    echo "$(date +"%F %T") Started compresion ($COMPRESION%) ${backup}"
	$BIN --quality $COMPRESION "${origen}" "${origen}"    
    
    ORIGINAL_FILESIZE=$(stat -c%s "$backup")
    COMPRESS_FILESIZE=$(stat -c%s "$origen")
    
    SAVED_BYTES=$(($ORIGINAL_FILESIZE-$COMPRESS_FILESIZE))
    
    echo "$(date +"%F %T") Saved ${SAVED_BYTES} bytes ${origen}"
    
  else
  	echo "$(date +"%F %T") Backup exists ${backup}"
    
  fi
  
done <$LIST

echo "$(date +"%F %T") Done folder $1"