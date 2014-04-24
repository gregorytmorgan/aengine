#!/usr/bin/env /bin/bash

if [ "$1" == "" ]; then
	BOT1='python sample_bots/python/HunterBot.py'
else
	echo "Using $1 as BOT1"
    BOT1=$1
fi

if [ "$2" == "" ]; then
	BOT2='python sample_bots/python/LeftyBot.py'
else
	echo "Using $2 as BOT2"
    BOT2=$2
fi

if [ "$3" == "" ]; then
	BOT3='python sample_bots/python/HunterBot.py'
else
	echo "Using $3 as BOT3"
    BOT3=$3
fi

if [ "$4" == "" ]; then
	BOT4='python sample_bots/python/GreedyBot.py'
else
	echo "Using $4 as BOT4"
    BOT4=$4
fi


./playgame.py --player_seed 42 --end_wait=0.25 --verbose --log_dir game_logs --turns 10  --map_file maps/maze/maze_04p_01.map "$BOT1" "$BOT2" "$BOT3" "$BOT4"
