#!/bin/bash

yourfilenames=`ls ./*.flac`
for eachfile in $yourfilenames
do
   echo $eachfile
   ffmpeg -i $eachfile $eachfile.mp3
done
