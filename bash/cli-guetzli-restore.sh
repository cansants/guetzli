#/bin/bash

VERSION="1.0"
BIN="/traspas/guetzli/lib/guetzli"
COMPRESION=86
BKPSUFIX=".copy"
LIST="/tmp/.cli-guetzli-restore.list"


if [ $# -lt 1 ]
then
	echo "Usage : $0 /path/to/folder"
    exit
fi

echo "$(date +"%F %T") Starting restore backup images on $1"

## Borramos la lista
rm $LIST
echo "$(date +"%F %T") Delete previous list"

## buscamos las imagenes 
find $1 \( -iname \*$BKPSUFIX \) -exec ls {} \; > $LIST


while read img; do


  backup=$img
  original=${backup%$BKPSUFIX}

  if [ -f "${backup}" ]; then
  
    # restauramos backup
    mv "${backup}" "${original}"
    echo "$(date +"%F %T") Restored Backup ${original}"
    
  else
  	echo "$(date +"%F %T") No exists backup ${backup}"
    
  fi
  
done <$LIST

echo "$(date +"%F %T") Done restore folder $1"