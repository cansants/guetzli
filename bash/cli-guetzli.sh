#/bin/bash

VERSION="1.0"
BIN="/traspas/guetzli/lib/guetzli"
BIN_PNG="optipng"
COMPRESION=86
BKPSUFIX=".copy"
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
#find $1 \( -iname \*.jpg \)  -exec ls {} \; > $LIST


while read img; do

  origen=$img
  backup="$img$BKPSUFIX"

  #echo "$(date +"%F %T") Next file $origen"

  if [ ! -f "${backup}" ]; then
  
    # creamos backup
    cp "${origen}" "${backup}"
    echo "$(date +"%F %T") Backup created $backup"
    
    if [[ $(file -b "${origen}") =~ ^'JPEG ' ]]; then 
    
      ## Iniciamos la compresion JPG
      echo "$(date +"%F %T") Started compresion ($COMPRESION%) ${origen}"
	  ${BIN} --quality ${COMPRESION} "${origen}" "${origen}"    
    
    else
    
      ## Iniciamos la compresion JPG
      echo "$(date +"%F %T") Started compresion ($COMPRESION%) ${origen}"
      ${BIN_PNG} -quiet -o7 "${origen}"  
    
    fi
    
    ORIGINAL_FILESIZE=$(stat -c%s "$backup")
    COMPRESS_FILESIZE=$(stat -c%s "$origen")
    
    SAVED_BYTES=$(($ORIGINAL_FILESIZE-$COMPRESS_FILESIZE))
    
    if (( SAVED_BYTES < 0 )); then
      ## No ahorramos bytes!
      echo "$(date +"%F %T") Restored backup for ${origen}"
      cp "${backup}" "${origen}"
      
    else
      echo "$(date +"%F %T") Saved ${SAVED_BYTES} bytes ${origen}"	
      
    fi
    
    
    
  else
  	echo "$(date +"%F %T") Backup exists ${backup}"
    
  fi
  
done <$LIST

echo "$(date +"%F %T") Done folder $1"