#!/usr/bin/env bash
# Placeholder sound effects and ambiences for a timeline, made with ffmpeg: synthesis for machines and
# rooms, and the script's own generated voice lines (reversed and filtered until indistinct) for TV,
# speakerphone and crowd walla. No samples, no licences. Replace with recordings when there are some;
# the manifest IDs stay.
#
# Usage: tools/synth-sfx.sh <output-dir> <voice.mp3>...
set -euo pipefail
out="${1:?output directory}"; shift
(($# > 0)) || { echo "give some voice files for the walla" >&2; exit 1; }
mkdir -p "$out"
tmp="$(mktemp -d)"; trap 'rm -rf "$tmp"' EXIT
SR=44100
enc() { ffmpeg -y -v error "$@"; }

# Fades and a limiter on every file; beds get long fades so they can be trimmed anywhere.
finish() { # in.wav name seconds fadeSeconds
  local st; st=$(echo "$3-$4" | bc | sed 's/^\./0./')
  enc -i "$1" -af "atrim=0:$3,afade=t=in:d=$4,afade=t=out:st=$st:d=$4,alimiter=limit=0.9" -ac 2 -ar $SR -b:a 192k "$out/$2.mp3"
}
tone() { # name seconds expression
  enc -f lavfi -i "aevalsrc=exprs='$3':s=$SR:d=$2" "$tmp/$1.wav"
}
noise() { # name seconds color filters
  enc -f lavfi -i "anoisesrc=d=$2:c=$3:r=$SR:a=0.5" -af "$4" "$tmp/$1.wav"
}
mix2() { # a b out [weights]
  enc -i "$tmp/$1.wav" -i "$tmp/$2.wav" -filter_complex "amix=inputs=2:normalize=0:weights=${4:-1 1}" "$tmp/$3.wav"
}

# ---- Rooms and machines (30 s beds) ----
noise hvac 30 brown "lowpass=f=260,volume=1.4"
tone hum120 30 "0.03*sin(2*PI*120*t)"
mix2 hvac hum120 office-hvac; finish "$tmp/office-hvac.wav" amb-office-hvac 30 1.5

noise heater 30 pink "highpass=f=180,lowpass=f=1800,volume=0.5"
tone hum60 30 "0.05*sin(2*PI*60*t)+0.02*sin(2*PI*180*t)"
mix2 heater hum60 space-heater; finish "$tmp/space-heater.wav" amb-space-heater 30 1.5

noise fan 30 brown "bandpass=f=420:w=300,tremolo=f=19:d=0.15,volume=1.6"; finish "$tmp/fan.wav" amb-heater-fan 30 1.5

# Old pickup idling: low firing pulse plus filtered rumble.
tone idle 30 "0.35*(sin(2*PI*29*t)+0.5*sin(2*PI*58*t)+0.25*sin(2*PI*87*t))*(0.65+0.35*sin(2*PI*14.5*t))"
noise rumble 30 brown "lowpass=f=180,tremolo=f=14.5:d=0.5,volume=1.2"
mix2 idle rumble truck-idle; finish "$tmp/truck-idle.wav" amb-truck-idle 30 1.5

# ---- One-shots ----
# Impact wrench: two bursts of hammer pings over a motor whine.
tone wrench 2 "(lt(t,0.95)+gte(t,1.25)*lt(t,1.75))*(0.55*exp(-mod(t,0.045)*260)*sin(2*PI*2400*t)+0.25*exp(-mod(t,0.045)*90)*sin(2*PI*620*t)+0.12*sin(2*PI*(950*t+40*sin(2*PI*3*t))))"
noise wrenchnoise 2 white "highpass=f=1500,volume=0.25"
enc -i "$tmp/wrenchnoise.wav" -f lavfi -i "aevalsrc=exprs='(lt(t,0.95)+gte(t,1.25)*lt(t,1.75))*exp(-mod(t,0.045)*200)':s=$SR:d=2" -filter_complex amultiply "$tmp/wrenchhit.wav"
mix2 wrench wrenchhit wrench-all; finish "$tmp/wrench-all.wav" sfx-tool.impact-wrench 2 0.05

# Engine off: the idle pulse slows and dies with a shudder.
tone engoff 2 "0.6*(sin(2*PI*(29*t-6*t*t))+0.5*sin(2*PI*(58*t-12*t*t)))*if(lt(t,0.25),1,exp(-(t-0.25)*3.2))*(0.7+0.3*sin(2*PI*9*t))"
finish "$tmp/engoff.wav" sfx-vehicle.engine-off 2 0.05

# Hot engine ticking as it cools: irregular metallic ticks.
expr=""; for ti in 0.15 0.8 1.35 2.2 2.9 4.1 5.3; do expr+="+gte(t,$ti)*exp(-(t-$ti)*600)*sin(2*PI*3300*t)"; done
tone ticks 6 "0.5*(0${expr})"; finish "$tmp/ticks.wav" sfx-vehicle.engine-tick 6 0.05

# EV low-speed pedestrian sound: a soft two-tone hum rising with speed.
tone ev 4 "0.18*(sin(2*PI*(360*t+18*t*t))+0.6*sin(2*PI*(540*t+27*t*t)))*min(1,t/0.6)"
finish "$tmp/ev.wav" sfx-vehicle.ev-pedestrian-hum 4 0.5

# Mechanic's creeper rolling out on concrete.
noise roll 1.6 brown "bandpass=f=320:w=250,tremolo=f=13:d=0.6,volume=2.2"; finish "$tmp/roll.wav" sfx-foley.creeper-roll 1.6 0.3

# Sleigh bells: shaken jingles, bright inharmonic partials plus a shaker.
tone jingle 3.2 "exp(-mod(t,0.22)*20)*(0.2*sin(2*PI*2950*t)+0.16*sin(2*PI*4120*t)+0.12*sin(2*PI*5330*t)+0.08*sin(2*PI*6710*t))*(0.8+0.2*sin(2*PI*1.3*t))"
noise shake 3.2 white "highpass=f=5000,volume=0.6"
enc -i "$tmp/shake.wav" -f lavfi -i "aevalsrc=exprs='exp(-mod(t,0.22)*30)':s=$SR:d=3.2" -filter_complex amultiply "$tmp/shaker.wav"
mix2 jingle shaker bells "1 0.7"; enc -i "$tmp/bells.wav" -af "aecho=0.8:0.6:40:0.3" "$tmp/bells2.wav"; finish "$tmp/bells2.wav" sfx-bells.sleigh 3.2 0.6

# Crows, far off: three rough caws in a cold field, twice.
caw="(sin(2*PI*(560*T-300*T*T))+0.6*sin(2*PI*(1120*T-600*T*T))+0.4*sin(2*PI*(1680*T-900*T*T)))*sin(PI*min(T/0.32,1))*lt(T,0.32)"
expr=""; for c in 2.0 2.45 2.95 13.0 13.5 22.5 23.0 23.5; do expr+="+$(echo "$caw" | sed "s/T/(t-$c)/g")*gte(t,$c)"; done
tone crowtone 30 "0.25*(0${expr})"
enc -i "$tmp/crowtone.wav" -af "acrusher=bits=8:mix=0.25,highpass=f=300,lowpass=f=2600,aecho=0.8:0.5:180|420:0.35|0.2,volume=0.6" "$tmp/crows.wav"
finish "$tmp/crows.wav" amb-crows-distant 30 1.0

# ---- Voices, made indistinct ----
# Every line once, reversed: speech rhythm without words.
inputs=(); for f in "$@"; do inputs+=(-i "$f"); done
enc "${inputs[@]}" -filter_complex "concat=n=$#:v=0:a=1,aresample=$SR,pan=mono|c0=c0,areverse" "$tmp/speech.wav"
len=$(ffprobe -v error -show_entries format=duration -of csv=p=0 "$tmp/speech.wav")

# Boss over the car speaker.
enc -i "$tmp/speech.wav" -af "atrim=0:6,asetrate=$SR*0.92,aresample=$SR,highpass=f=300,lowpass=f=1400,acompressor=threshold=-20dB:ratio=4,volume=1.4" "$tmp/murmur.wav"
finish "$tmp/murmur.wav" sfx-voice.murmur-indistinct 6 0.2

# A television in the living room: voices through a small speaker, looped to 30 s.
enc -stream_loop -1 -i "$tmp/speech.wav" -af "atrim=0:30,highpass=f=180,lowpass=f=2400,aecho=0.6:0.4:25:0.25,volume=0.8" "$tmp/tv.wav"
finish "$tmp/tv.wav" amb-tv-low 30 1.0

# Crowd waiting in the square: six layers at different pitches and offsets, plus outdoor air.
layers=(); filters=""; i=0
for spec in "0.82:0" "0.9:7" "0.97:13" "1.05:19" "1.12:3" "1.2:11"; do
  rate="${spec%%:*}"; off="${spec#*:}"
  layers+=(-stream_loop -1 -i "$tmp/speech.wav")
  filters+="[$i:a]atrim=start=$(echo "$off % $len" | bc):duration=40,asetpts=PTS-STARTPTS,asetrate=$SR*$rate,aresample=$SR,atrim=0:30[v$i];"
  i=$((i+1))
done
labels=""; for ((j=0;j<i;j++)); do labels+="[v$j]"; done
enc "${layers[@]}" -filter_complex "${filters}${labels}amix=inputs=$i:normalize=0,volume=0.35,lowpass=f=3200,aecho=0.7:0.6:60|130:0.3|0.2" "$tmp/walla.wav"
noise air 30 pink "lowpass=f=900,volume=0.25"
mix2 walla air crowd; finish "$tmp/crowd.wav" amb-crowd-murmur 30 1.5

# Shop radio: the store carol through a small speaker (inherits that recording's licence).
carol="$(dirname "$out")/catalog/mus-store-carol.mp3"
if [[ -f "$carol" ]]; then
  enc -stream_loop -1 -i "$carol" -af "atrim=0:30,highpass=f=450,lowpass=f=3500,acompressor=threshold=-22dB:ratio=5" "$tmp/radio.wav"
  finish "$tmp/radio.wav" amb-shop-radio 30 1.0
fi

# ---- Loudness: beds sit under dialogue, distant things lower; one-shots read clearly. ----
level() { # name LUFS
  [[ -f "$out/$1.mp3" ]] || return 0
  enc -i "$out/$1.mp3" -af "loudnorm=I=$2:TP=-2:LRA=11" -ar $SR -b:a 192k "$tmp/norm.mp3" && mv "$tmp/norm.mp3" "$out/$1.mp3"
}
for spec in amb-office-hvac:-30 amb-space-heater:-30 amb-heater-fan:-30 amb-truck-idle:-27 amb-shop-radio:-26 \
            amb-tv-low:-30 amb-crowd-murmur:-24 amb-crows-distant:-32 \
            sfx-tool.impact-wrench:-18 sfx-vehicle.engine-off:-20 sfx-vehicle.engine-tick:-26 \
            sfx-vehicle.ev-pedestrian-hum:-24 sfx-foley.creeper-roll:-22 sfx-bells.sleigh:-20 sfx-voice.murmur-indistinct:-22; do
  level "${spec%%:*}" "${spec#*:}"
done

echo "wrote $(ls "$out" | wc -l | tr -d ' ') files to $out"
