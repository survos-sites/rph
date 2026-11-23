#!/usr/bin/env bash
# Original placeholder cues for a timeline, synthesized with ffmpeg (no samples, no licences).
# Usage: tools/synth-cues.sh <output-dir>
# The trailer theme is a rising C-E-G-B that stops on the unresolved leading tone; only `theme-resolve`
# lands on the C above. Replace these with recorded score when there is one; the manifest IDs stay.
set -euo pipefail
out="${1:?output directory}"; mkdir -p "$out"

# One piano-ish note: decaying fundamental plus two softer harmonics.
note() { # freq seconds
  echo "(sin(2*PI*$1*t)*exp(-2.2*t)+0.45*sin(4*PI*$1*t)*exp(-3.5*t)+0.2*sin(6*PI*$1*t)*exp(-5*t))*0.5*lt(t,$2)"
}
render() { # name seconds expression [filters]
  ffmpeg -y -v error -f lavfi -i "aevalsrc=exprs='$3':s=44100:d=$2" -af "${4:-anull},afade=t=out:st=$(echo "$2-0.3" | bc | sed "s/^\./0./"):d=0.3,alimiter=limit=0.9" -ac 2 -b:a 192k "$out/$1.mp3"
}
C4=261.63; E4=329.63; G4=392.00; B4=493.88; C5=523.25; C3=130.81; E3=164.81

tmp="$(mktemp -d)"
for spec in "c4:$C4" "e4:$E4" "g4:$G4" "b4:$B4" "c5:$C5" "c3:$C3" "e3:$E3"; do
  name="${spec%%:*}"; f="${spec#*:}"
  ffmpeg -y -v error -f lavfi -i "aevalsrc=exprs='$(note $f 4)':s=44100:d=4" -ac 2 "$tmp/$name.wav"
done
mix() { # name seconds note:delayms ...
  local name="$1" dur="$2"; shift 2
  local inputs=() filters="" i=0
  for spec in "$@"; do inputs+=(-i "$tmp/${spec%%:*}.wav"); filters+="[$i:a]adelay=${spec#*:}|${spec#*:}[a$i];"; i=$((i+1)); done
  local labels=""; for ((j=0;j<i;j++)); do labels+="[a$j]"; done
  ffmpeg -y -v error "${inputs[@]}" -filter_complex "${filters}${labels}amix=inputs=$i:normalize=0,volume=0.8,atrim=0:$dur,afade=t=out:st=$(echo "$dur-0.6" | bc | sed "s/^\./0./"):d=0.6,alimiter=limit=0.9" -b:a 192k "$out/$name.mp3"
}
mix theme-in 3.6 c4:0 e4:550 g4:1100 b4:1650
cp "$out/theme-in.mp3" "$out/theme-play.mp3"
mix theme-hold 3.0 c4:0 e4:0 g4:0 b4:0
mix theme-resolve 5.0 c4:0 e4:450 g4:900 b4:1350 c5:1900 c3:1900 e3:1900
mix comedy-resolve 1.2 c3:0
mix comedy-sting 0.9 c5:0
# Held low string note (the music "drops" under the sunset) and a rising swell under the countdown.
render theme-drop 6 "0.22*sin(2*PI*$E3*t)*(0.6+0.4*sin(2*PI*0.25*t))*min(1,t/1.5)+0.1*sin(2*PI*$B4/2*t)*min(1,t/2)"
render theme-swell 4 "(0.12*sin(2*PI*$C4*t)+0.1*sin(2*PI*$G4*t)+0.08*sin(2*PI*$B4*t))*pow(t/4,2)"
# Phone buzzing on a dashboard: three short 165 Hz bursts.
render phone-vibrate 2.4 "0.6*sin(2*PI*165*t)*(0.7+0.3*sin(2*PI*40*t))*lt(mod(t,0.8),0.45)*lt(t,2.3)" "highpass=f=90"
rm -rf "$tmp"
ls "$out"
