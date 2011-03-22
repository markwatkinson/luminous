#!/bin/bash


function encode {
    
  if [[ $(file "$1") =~ "FLAC audio bitstream data" ]]; then

      OUTF=`echo "$1" | sed s/\.flac$/.mp3/g`

      ARTIST=`metaflac "$1" --show-tag=ARTIST | sed s/.*=//g`
      TITLE=`metaflac "$1" --show-tag=TITLE | sed s/.*=//g`
      ALBUM=`metaflac "$1" --show-tag=ALBUM | sed s/.*=//g`
      GENRE=`metaflac "$1" --show-tag=GENRE | sed s/.*=//g`
      TRACKNUMBER=`metaflac "$1" --show-tag=TRACKNUMBER | sed s/.*=//g`
      DATE=`metaflac "$1" --show-tag=DATE | sed s/.*=//g`

      flac -c -d "$1" | lame --abr 128 -s 44.1 - "$OUTF" --tt "$TITLE" --ta "$ARTIST" --tl "$ALBUM" --ty "$DATE" --tg "$GENRE" --tn "$TRACKNUMBER"

  elif [[ $(file "$1") =~ "Ogg data, Vorbis audio" ]]; then
      OUTF=`echo "$1" | sed s/\.ogg$/.mp3/g`
      WAV=`echo "$1" | sed s/\.ogg$/.wav/g`
      ALBUM=`ogginfo "$1" | grep ALBUM= | sed s/.*=//`
      ARTIST=`ogginfo "$1" | grep ARTIST= | sed s/.*=//`
      DATE=`ogginfo "$1" | grep DATE= | sed s/.*=//`
      GENRE=`ogginfo "$1" | grep GENRE | sed s/.*=//`
      TITLE=`ogginfo "$1" | grep TITLE= | sed s/.*=//`
      TRACKNUMBER=`ogginfo "$1" | grep TRACKNUMBER= | sed s/.*=//`

      oggdec -o "$WAV" "$1"

      lame --abr 128 -s 44.1 "$WAV" "$OUTF" --tt "$TITLE" --ta "$ARTIST" --tl "$ALBUM" --ty "$DATE" --tg "$GENRE" --tn "$TRACKNUMBER"
      rm "$WAV"
	 
  else
    echo "unrecognised file $1"

  fi
}


function iter {

for f in $(ls "$1")
do
  if [ -d "$1/$f" ];
  then
    iter "$1/$f";
  elif [ -f "$1/$f" ]; then
    encode "$1/$f";
 fi
done

}
  
  
  
SAVEIFS=$IFS
IFS=$(echo -en "\n\b")
for a in "$@"
  do
    if [ -d "$a" ]; then
      iter "$a"
    elif [ -f "$a" ]; then
      encode "$a";
    fi
  done
IFS=$SAVEIFS

  
  
  
