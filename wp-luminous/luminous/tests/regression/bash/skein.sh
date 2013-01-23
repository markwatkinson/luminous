#!/bin/bash

#
# Author: Matt Tomasello, 2011
# Based on the JavaScript implementation written by Thomas Mueller, 2008-2010
# which was based on the C reference implementation written by Doug Whitting,
# 2008. Special thanks to Mike Drob for the zero-fill right-shift algorithm.
# This algorithm and source code is released to the public domain.
#

# 64-bit architecture check
declare ARCH=0
for (( i=0; i<8; i++ )); do
        j=$((2**i))
        if [[ $((1<<j)) -eq 1 ]]; then
                ARCH=$j
                break;
        fi
done
if [[ $ARCH -lt 64 ]]; then
        echo 'This program is written for 64-bit architectures.'
        exit 1
fi

# Globals
readonly WORD16=0xFFFF
readonly WORD32=0xFFFFFFFF
readonly N=0x7FFFFFFFFFFFFFFF
declare DEBUG=0
declare PASS
declare STR2HEX
declare HEX2STR
declare BYTES2HEX
declare ZF
declare HI
declare LO
declare -a SL
declare -a SR
declare -a STR2BYTES
declare -a HEX2BYTES
declare -a MSG
declare -a TWEAK
declare -a C
declare -a HASH
readonly -a SKEIN=(83 72 65 51 1 0 0 0 0 2)
readonly -a R=(46 36 19 37 33 42 14 27 17 49 36 39 44 56 54 9 39 30 34 24 13 17 10 50 25 29 39 43 8 22 56 35)

# Only displays messages when DEBUG is enabled
function debug {
        if [[ "$DEBUG" -eq 1 && -n "$1" ]]; then
                echo "$1" >&2
        fi
}

# Displays usage info
function usage {
        echo 'Author: Matt Tomasello, 2011'
        echo 'Based on the JavaScript implementation written by Thomas Mueller, 2008-2010'
        echo 'which was based on the C reference implementation written by Doug Whitting,'
        echo '2008. Special thanks to Mike Drob for the zero-fill right-shift algorithm.'
        echo
        echo 'This algorithm and source code is released to the public domain.'
        echo
        echo 'This program will calculate the Skein 512-512 hash of STDIN using version 1.3 of'
        echo 'the Skein algorithm.'
        echo
        echo 'Usage: cat FILE | skein [ARGS]'
        echo "       echo 'some-string' | skein [ARGS]"
        echo
        echo 'Valid arguments may start with one or two hyphens, and include:'
        echo '  -h, -help     Display this help information'
        echo '  -selftest     Perform self-test'
        echo '  -debug        Display short debug information on STDERR'
        echo
        exit
}

# Converts hex-string like '62FF0ACC' into ASCII string
function hex2str {
        HEX2STR=''
        if [[ -n "$1" && ${#1}%2 -eq 0 ]]; then
                for (( i=0; i<${#1}; i=i+2 )); do
                        HEX2STR=$HEX2STR$(echo -e "\\x${1:i:2}")
                done
        fi
}

# Converts string like 'foobar' into hex-string
# Result may be decoded with hex2str
function str2hex {
        STR2HEX=''
        if [[ -n "$1" ]]; then
                for (( i=0; i<${#1}; i++ )); do
                        STR2HEX=$STR2HEX$(printf '%x' "'${1:i:1}")
                done
        fi
}

# Converts hex-string into array of bytes
function hex2bytes {
        HEX2BYTES=()
        if [[ -n "$1" && ${#1}%2 -eq 0 ]]; then
                for (( i=0,j=0; j<${#1}; i++,j=j+2 )); do
                        HEX2BYTES[i]=$(printf '%d' "'$(echo -e "\\x${1:j:2}")")
                done
        fi
}

# Converts array of bytes into hex-string
function bytes2hex {
        BYTES2HEX=''
        local bytes=("${!1}")
        if [[ "${#bytes[@]}" -gt 0 ]]; then
                for (( i=0; i<${#bytes[@]}; i++ )); do
                        local char=$(printf '%x' "${bytes[i]}")
                        BYTES2HEX=$BYTES2HEX${char:1:2}
                done
        fi
}

# Converts string into array of bytes (character-values)
function str2bytes {
        STR2BYTES=()
        if [[ -n "$1" ]]; then
                for (( i=0; i<${#1}; i++ )); do
                        local c=$(printf '%d' "'${1:i:1}")
                        STR2BYTES[i]=$(( c&255 ))
                done
        fi
}

# Left shift
function shift_left {
        SL[0]=0
        SL[1]=0
        local lo=${1:-0}
        local hi=${2:-0}
        local n=${3:-0}
        if [[ ! "$lo" -eq 0 || ! "$hi" -eq 0 ]]; then
                if [[ "$n" -gt 32 ]]; then
                        SL[0]=$(( (hi<<(n-32))&WORD32 ))
                        SL[1]=0
                elif [[ "$n" -eq 32 ]]; then
                        SL[0]=$hi
                        SL[1]=0
                elif [[ "$n" -eq 0 ]]; then
                        SL[0]=$lo
                        SL[1]=$hi
                else
                        zf_shift_right hi $(( 32-n ))
                        SL[0]=$(( ((lo<<n)|ZF)&WORD32 ))
                        SL[1]=$(( (hi<<n)&WORD32 ))
                fi
        fi
}

# Right shift
function shift_right {
        SR[0]=0
        SR[1]=0
        local lo=${1:-0}
        local hi=${2:-0}
        local n=${3:-0}
        if [[ ! "$lo" -eq 0 || ! "$hi" -eq 0 ]]; then
                if [[ "$n" -gt 32 ]]; then
                        zf_shift_right lo $(( n-32 ))
                        SR[0]=0
                        SR[1]=$ZF
                elif [[ "$n" -eq 32 ]]; then
                        SR[0]=0
                        SR[1]=$lo
                elif [[ "$n" -eq 0 ]]; then
                        SR[0]=$lo
                        SR[1]=$hi
                else
                        zf_shift_right lo n
                        SR[0]=$ZF
                        zf_shift_right hi n
                        SR[1]=$(( ((lo<<(32-n))|ZF)&WORD32  ))
                fi
        fi
}

# Xor
function xor {
        XOR[0]=0
        XOR[1]=0
        local x0=${1:-0}
        local x1=${2:-0}
        local y0=${3:-0}
        local y1=${4:-0}
        XOR[0]=$(( (x0^y0)&WORD32 ))
        XOR[1]=$(( (x1^y1)&WORD32 ))
}

# Add
function add {
        ADD[0]=0
        ADD[1]=0
        local x0=${1:-0}
        local x1=${2:-0}
        local y0=${3:-0}
        local y1=${4:-0}
        if [[ -z "$3" || -z "$4" ]]; then
                ADD[0]=$x0
                ADD[1]=$x1
        else
                local lsw=$(( (x1&WORD16)+(y1&WORD16) ))
                zf_shift_right x1 16
                local xs=$ZF
                zf_shift_right y1 16
                local ys=$ZF
                zf_shift_right lsw 16
                local msw=$(( xs + ys + ZF ))
                local lo=$(( ((msw&WORD16)<<16)|(lsw&WORD16) ))
                zf_shift_right msw 16
                lsw=$(( (x0&WORD16)+(y0&WORD16)+ZF ))
                zf_shift_right x0 16
                xs=$ZF
                zf_shift_right y0 16
                ys=$ZF
                zf_shift_right lsw 16
                msw=$(( xs + ys + ZF ))
                local hi=$(( ((msw&WORD16)<<16)|(lsw&WORD16) ))
                ADD[0]=$(( hi&WORD32 ))
                ADD[1]=$(( lo&WORD32 ))
        fi
}

# Zero-fill right-shift
function zf_shift_right {
        ZF=0
        if [[ -n "$1" && -n "$2" ]]; then
                ZF=$(( ($1 >> $2) & (N>>~-$2) ))
        fi
}

function block {
        local offset=${1:-0}
        local b=("${!2}")
        local nil=${3:-0}
        local x=()
        local t=()
        if [[ "$nil" -eq 1 ]]; then
                unset b
        fi
        C[16]=466688986
        C[17]=2851871266
        for (( i=0; i<8; i++ )); do
                for (( j=7,k=offset+i*8+7; j>=0; j--,k-- )); do
                        shift_left ${t[i*2]} ${t[i*2+1]} 8
                        t[i*2]=${SL[0]}
                        bk=$(( b[k] & 255 ))
                        t[i*2+1]=$(( SL[1]|(bk&255) ))
                done
                add t[i*2] t[i*2+1] C[i*2] C[i*2+1]
                x[i*2]=${ADD[0]}
                x[i*2+1]=${ADD[1]}
                xor ${C[16]} ${C[17]} ${C[i*2]} ${C[i*2+1]}
                C[16]=${XOR[0]}
                C[17]=${XOR[1]}
        done
        add x[10] x[11] TWEAK[0] TWEAK[1]
        x[10]=${ADD[0]}
        x[11]=${ADD[1]}
        add x[12] x[13] TWEAK[2] TWEAK[3]
        x[12]=${ADD[0]}
        x[13]=${ADD[1]}
        xor ${TWEAK[0]} ${TWEAK[1]} ${TWEAK[2]} ${TWEAK[3]}
        TWEAK[4]=${XOR[0]}
        TWEAK[5]=${XOR[1]}
        for (( round=1; round<=18; round++ )); do
                local p=$(( 16-((round&1)<<4) ))
                for (( i=0; i<16; i++ )); do
                        local m=$(( 2*((i+(1+i+i)*(i>>2))&3) ))
                        local n=$(( (1+i+i)&7 ))
                        local r0=${R[p+i]}
                        add x[m*2] x[m*2+1] x[n*2] x[n*2+1]
                        x[m*2]=${ADD[0]}
                        x[m*2+1]=${ADD[1]}
                        shift_left ${x[n*2]} ${x[n*2+1]} r0
                        shift_right ${x[n*2]} ${x[n*2+1]} $(( 64-r0 ))
                        xor ${SL[0]} ${SL[1]} ${SR[0]} ${SR[1]}
                        x[n*2]=${XOR[0]}
                        x[n*2+1]=${XOR[1]}
                        xor ${x[n*2]} ${x[n*2+1]} ${x[m*2]} ${x[m*2+1]}
                        x[n*2]=${XOR[0]}
                        x[n*2+1]=${XOR[1]}
                done
                for (( i=0; i<8; i++ )); do
                        local ri9=$(( (round+i)%9 ))
                        add ${x[i*2]} ${x[i*2+1]} ${C[ri9*2]} ${C[ri9*2+1]}
                        x[i*2]=${ADD[0]}
                        x[i*2+1]=${ADD[1]}
                done
                local r3=$(( round%3 ))
                add ${x[10]} ${x[11]} ${TWEAK[r3*2]} ${TWEAK[r3*2+1]}
                x[10]=${ADD[0]}
                x[11]=${ADD[1]}
                local r13=$(( (round+1)%3 ))
                add ${x[12]} ${x[13]} ${TWEAK[r13*2]} ${TWEAK[r13*2+1]}
                x[12]=${ADD[0]}
                x[13]=${ADD[1]}
                add x[14] x[15] 0 round
                x[14]=${ADD[0]}
                x[15]=${ADD[1]}
        done
        for (( i=0; i<8; i++ )); do
                xor ${t[i*2]} ${t[i*2+1]} ${x[i*2]} ${x[i*2+1]}
                C[i*2]=${XOR[0]}
                C[i*2+1]=${XOR[1]}
        done
}

# Call to generate Skein-512-512 hash of string
function hash {
        C=()
        str2hex "$1"
        str2bytes "$1"
        debug "String in hex: ${STR2HEX[*]}"
        debug "String bytes: ${STR2BYTES[*]}"
        TWEAK=(0 32 3288334336 0)
        block 0 SKEIN[@]
        TWEAK=(0 0 1879048192 0)
        local len=${#STR2BYTES[@]}
        local pos=0
        for (( ; len>64; len-=64,pos+=64 )); do
                TWEAK[1]=$(( TWEAK[1]+64 ))
                block pos STR2BYTES[@]
                TWEAK[2]=805306368
        done
        TWEAK[1]=$(( TWEAK[1]+len ))
        TWEAK[2]=$(( TWEAK[2]|2147483648 ))
        block pos STR2BYTES[@]
        TWEAK[1]=8
        TWEAK[2]=4278190080
        block 0 0 1
        HASH=()
        for (( i=0; i<64; i++ )); do
                local is3=$(( i>>3 ))
                shift_right ${C[is3*2]} ${C[is3*2+1]} $(( (i&7)*8 ))
                local b=$(( (SR[1]&255)+256 ))
                HASH[i]=$b
        done
        bytes2hex HASH[@]
        echo $BYTES2HEX
}

# Checks that all functions operate correctly
function selftest {
        PASS=1
        local hex='666f6f626172'
        local str='foobar'
        local bytes=(102 111 111 98 97 114)
        local chk=0
        local msg=''

        msg='Testing hex2str:'
        hex2str "$hex"
        if [[ "$HEX2STR" = "$str" ]]; then
                msg="$msg Success"
        else
                PASS=0
                msg="$msg Failed with ${HEX2STR[@]}"
        fi
        echo "$msg"

        msg='Testing str2hex:'
        str2hex "$str"
        if [[ "$STR2HEX" = "$hex" ]]; then
                msg="$msg Success"
        else
                PASS=0
                msg="$msg Failed with ${STR2HEX[@]}"
        fi
        echo "$msg"

        msg='Testing hex2bytes:'
        hex2bytes "$hex"
        chk=1
        for (( i=0; i<${#HEX2BYTES[@]}; i++ )); do
                [[ "${HEX2BYTES[i]}" -eq "${bytes[i]}" ]] || chk=0
        done
        if [[ $chk -eq 1 ]]; then
                msg="$msg Success"
        else
                PASS=0
                msg="$msg Failed with ${HEX2BYTES[@]}"
        fi
        echo "$msg"

        msg='Testing str2bytes:'
        str2bytes "$str"
        chk=1
        for (( i=0; i<${#STR2BYTES[@]}; i++ )); do
                [[ "${STR2BYTES[i]}" -eq "${bytes[i]}" ]] || chk=0
        done
        if [[ $chk -eq 1 ]]; then
                msg="$msg Success"
        else
                PASS=0
                msg="$msg Failed with ${STR2BYTES[@]}"
        fi
        echo "$msg"

        msg='Testing shift_left:'
        shift_left 0xF0F0F0F0 0xF0F0F0F0 4
        if [[ "${SL[0]}" -eq 0x0F0F0F0F && "${SL[1]}" -eq 0x0F0F0F00 ]]; then
                msg="$msg Success"
        else
                PASS=0
                msg="$msg Failed with ${SL[@]}"
        fi
        echo "$msg"

        msg='Testing shift_right:'
        shift_right 0xF0F0F0F0 0xF0F0F0F0 4
        if [[ "${SR[0]}" -eq 0x0F0F0F0F && "${SR[1]}" -eq 0x0F0F0F0F ]]; then
                msg="$msg Success"
        else
                PASS=0
                msg="$msg Failed with ${SR[@]}"
        fi
        echo "$msg"

        msg='Testing zf_shift_right:'
        zf_shift_right 0xF0F0F0F0 4
        if [[ "$ZF" -eq 0x0F0F0F0F ]]; then
                msg="$msg Success"
        else
                PASS=0
                msg="$msg Failed with $ZF"
        fi
        echo "$msg"

        msg='Testing add:'
        add 0x0FFFFFFF 0x0FFFFFFF 0xF0000000 0xF0000000
        if [[ "${ADD[0]}" -eq 0xFFFFFFFF && "${ADD[1]}" -eq 0xFFFFFFFF ]]; then
                msg="$msg Success"
        else
                PASS=0
                msg="$msg Failed with ${ADD[@]}"
        fi
        echo "$msg"

        msg='Testing xor:'
        xor 0xAAAAAAAA 0x55555555 0x55555555 0xAAAAAAAA
        if [[ "${XOR[0]}" -eq 0xFFFFFFFF && "${XOR[1]}" -eq 0xFFFFFFFF ]]; then
                msg="$msg Success"
        else
                PASS=0
                msg="$msg Failed with ${XOR[@]}"
        fi
        echo "$msg"
}

# Check for the few args this program supports
for ARG in $@; do
        if [[ "$ARG" =~ --?(h|help|\?) ]]; then
                usage
        elif [[ "$ARG" =~ --?selftest ]]; then
                selftest
                if [[ "$PASS" -eq 1 ]]; then
                        exit
                fi
                exit 1
        elif [[ "$ARG" =~ --?debug ]]; then
                DEBUG=1
        fi
done

DATA=`cat`
hash "$DATA"
 
