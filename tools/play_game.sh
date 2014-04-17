#!/usr/bin/env /bin/bash

if [ "$1" == "" ]; then
	BOT1='python sample_bots/python/HunterBot.py'
else
    BOT1=$1
fi

if [ "$2" == "" ]; then
	BOT2='python sample_bots/python/LeftyBot.py'
else
    BOT2=$2
fi


if [ "$3" == "" ]; then
	BOT3='python sample_bots/python/HunterBot.py'
else
    BOT3=$3
fi


if [ "$4" == "" ]; then
	BOT4='python sample_bots/python/GreedyBot.py'
else
    BOT1=$4
fi


./playgame.py --player_seed 42 --end_wait=0.25 --verbose --log_dir game_logs --turns 1000 --map_file maps/maze/maze_04p_01.map "$@" "$BOT1" "$BOT2" "$BOT3" "$BOT4"
