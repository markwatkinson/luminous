#!/bin/sh
#
# Some things this script could/should do when finished
#
# * detect whether it's a GNU compiler or not (for compiler settings)
# * command line options to...
#   - override the host settings (for cross compiles
#   - whether to do a debug build (with -g) or an optimized build (-O3 etc.)
# * detect whether the chosen backend is available (e.g. call sdl-config)
# * ....


# use environment vars if set
CXXFLAGS="$CXXFLAGS $CPPFLAGS"

# default lib behaviour yes/no/auto
_opengl=auto
_zlib=auto

# default option behaviour yes/no
_build_gl=yes
_build_windowed=yes
_build_sound=yes
_build_debugger=yes
_build_snapshot=yes
_build_joystick=yes
_build_cheats=yes
_build_static=no
_build_profile=no

# more defaults
_ranlib=ranlib
_install=install
_ar="ar cru"
_strip=strip
_mkdir="mkdir -p"
_echo=printf
_cat=cat
_rm="rm -f"
_rm_rec="$_rm -r"
_zip="zip -q"
_cp=cp
_win32path=""
_windres=windres
_sdlconfig=sdl-config
_sdlpath="$PATH"
_prefix=/usr/local
X_LIBS="/usr/X11R6/lib"

_srcdir=`dirname $0`

# TODO: We should really use mktemp(1) to determine a random tmp file name.
# However, that tool might not be available everywhere.
TMPO=${_srcdir}/stella-conf
TMPC=${TMPO}.cxx
TMPLOG=${_srcdir}/config.log

# For cross compiling
_host=""
_host_cpu=""
_host_vendor=""
_host_os=""
_host_prefix=""

cc_check() {
	echo >> "$TMPLOG"
	cat "$TMPC" >> "$TMPLOG"
	echo >> "$TMPLOG"
	echo "$CXX $TMPC -o $TMPO$EXEEXT $@" >> "$TMPLOG"
	rm -f "$TMPO$EXEEXT"
	( $CXX "$TMPC" -o "$TMPO$EXEEXT" "$@" ) >> "$TMPLOG" 2>&1
	TMP="$?"
	echo >> "$TMPLOG"
	return "$TMP"
}

echocheck () {
	echo_n "Checking for $@... "
}

#
# Check whether the given command is a working C++ compiler
#
test_compiler ()
{
cat <<EOF >tmp_cxx_compiler.cpp
class Foo {
	int a;
};
int main(int argc, char **argv)
{
	Foo *a = new Foo();
	delete a;
	return 0;
}
EOF

if test -n "$_host"; then
	# In cross-compiling mode, we cannot run the result
	eval "$1 -o tmp_cxx_compiler$EXEEXT tmp_cxx_compiler.cpp 2> /dev/null" && rm -f tmp_cxx_compiler$EXEEXT tmp_cxx_compiler.cpp
else
	eval "$1 -o tmp_cxx_compiler$EXEEXT tmp_cxx_compiler.cpp 2> /dev/null" && eval "./tmp_cxx_compiler 2> /dev/null" && rm -f tmp_cxx_compiler$EXEEXT tmp_cxx_compiler.cpp
fi
}

#
# Determine sdl-config
#
# TODO: small bit of code to test sdl useability
find_sdlconfig()
{
	echo_n "Looking for sdl-config... "
	sdlconfigs="$_sdlconfig:sdl-config:sdl11-config:sdl12-config"
	_sdlconfig=
	
	IFS="${IFS=   }"; ac_save_ifs="$IFS"; IFS="$SEPARATOR"
	done=0
	for path_dir in $_sdlpath; do
                #reset separator to parse sdlconfigs
                IFS=":"
		for sdlconfig in $sdlconfigs; do
			if test -x "$path_dir/$sdlconfig" ; then
				_sdlconfig="$path_dir/$sdlconfig"
				done=1
				break
			fi
		done
		if test $done -eq 1 ; then
			echo $_sdlconfig
			break
		fi
	done
	
	IFS="$ac_save_ifs"
	
	if test -z "$_sdlconfig"; then
		echo "none found!"
		exit 1
	fi
}

#
# Function to provide echo -n for bourne shells that don't have it
#
echo_n() 
{ 
	printf "$@"
}

#
# Greet user
#

echo "Running Stella configure..."
echo "Configure run on" `date` > $TMPLOG

#
# Check any parameters we received
#
# TODO:
# * Change --disable-mad / --enable-mad to the way it's done in autoconf:
#  That is, --without-mad / --with-mad=/prefix/to/mad. Useful for people
#  who have Mad/Vorbis/ALSA installed in a non-standard locations.
#

for parm in "$@" ; do
  if test "$parm" = "--help" || test "$parm" = "-help" || test "$parm" = "-h" ; then
    cat << EOF

Usage: $0 [OPTIONS]...

Configuration:
  -h, --help             display this help and exit

Installation directories:
  --prefix=DIR           use this prefix for installing stella  [/usr/local]
  --bindir=DIR           directory to install the stella binary [PREFIX/bin]
  --docdir=DIR           directory to install documentation     [PREFIX/share/doc/stella]
  --datadir=DIR          directory to install icons/data files  [PREFIX/share]

Optional Features:
  --enable-gl            enable/disable OpenGL rendering support [enabled]
  --disable-gl
  --enable-windowed      enable/disable windowed rendering modes [enabled]
  --disable-windowed
  --enable-sound         enable/disable sound support [enabled]
  --disable-sound
  --enable-debugger      enable/disable all debugger options [enabled]
  --disable-debugger
  --enable-joystick      enable/disable joystick support [enabled]
  --disable-joystick
  --enable-cheats        enable/disable cheatcode support [enabled]
  --disable-cheats
  --enable-shared        build shared binary [enabled]
  --enable-static        build static binary (if possible) [disabled]
  --disable-static
  --enable-profile       build binary with profiling info [disabled]
  --disable-profile

Optional Libraries:
  --with-sdl-prefix=DIR    Prefix where the sdl-config script is installed (optional)
  --with-zlib-prefix=DIR   Prefix where zlib is installed (optional)
  --x-libraries            Path to X11 libraries [${X_LIBS}]

Some influential environment variables:
  LDFLAGS	linker flags, e.g. -L<lib dir> if you have libraries in a
  		nonstandard directory <lib dir>
  CXX		C++ compiler command
  CXXFLAGS	C++ compiler flags
  CPPFLAGS	C++ preprocessor flags, e.g. -I<include dir> if you have
  		headers in a nonstandard directory <include dir>

EOF
    exit 0
  fi
done # for parm in ...

for ac_option in $@; do
    case "$ac_option" in
      --enable-gl)              _build_gl=yes        ;;
      --disable-gl)             _build_gl=no         ;;
      --enable-windowed)        _build_windowed=yes  ;;
      --disable-windowed)       _build_windowed=no   ;;
      --enable-sound)           _build_sound=yes     ;;
      --disable-sound)          _build_sound=no      ;;
      --enable-debugger)        _build_debugger=yes  ;;
      --disable-debugger)       _build_debugger=no   ;;
      --enable-joystick)        _build_joystick=yes  ;;
      --disable-joystick)       _build_joystick=no   ;;
      --enable-cheats)          _build_cheats=yes    ;;
      --disable-cheats)         _build_cheats=no     ;;
      --enable-shared)          _build_static=no     ;;
      --enable-static)          _build_static=yes    ;;
      --disable-static)         _build_static=no     ;;
      --enable-profile)         _build_profile=yes   ;;
      --disable-profile)        _build_profile=no    ;;
      --with-sdl-prefix=*)
        arg=`echo $ac_option | cut -d '=' -f 2`
        _sdlpath="$arg:$arg/bin"
        ;;
      --with-zlib-prefix=*)
        _prefix=`echo $ac_option | cut -d '=' -f 2`
        ZLIB_CFLAGS="-I$_prefix/include"
        ZLIB_LIBS="-L$_prefix/lib"
        ;;
      --x-libraries=*)
        arg=`echo $ac_option | cut -d '=' -f 2`
        X_LIBS="$arg"
        ;;
      --host=*)
        _host=`echo $ac_option | cut -d '=' -f 2`
        ;;
      --prefix=*)
        _prefix=`echo $ac_option | cut -d '=' -f 2`
        ;;
      --bindir=*)
        _bindir=`echo $ac_option | cut -d '=' -f 2`
        ;;
      --docdir=*)
        _docdir=`echo $ac_option | cut -d '=' -f 2`
        ;;
      --datadir=*)
        _datadir=`echo $ac_option | cut -d '=' -f 2`
        ;;
      *)
        echo "warning: unrecognised option: $ac_option"
        ;;
    esac;
done;

CXXFLAGS="$CXXFLAGS $DEBFLAGS"

case $_host in
#linupy)
#	_host_os=linux
#	_host_cpu=arm
#	;;
#arm-riscos-aof)
#	_host_os=riscos
#	_host_cpu=arm
#	;;
#ppc-amigaos)
#	_host_os=amigaos
#	_host_cpu=ppc
#	;;
gp2x)
	_host_os=gp2x
	_host_cpu=arm
	_host_prefix=arm-open2x-linux
	;;
mingw32-cross)
	_host_os=mingw32msvc
	_host_cpu=i386
	_host_prefix=i386-mingw32msvc
	;;
*)
	guessed_host=`$_srcdir/config.guess`
	_host_cpu=`echo $guessed_host | sed 's/^\([^-]*\)-\([^-]*\)-\(.*\)$/\1/'`
	_host_os=`echo $guessed_host | sed 's/^\([^-]*\)-\([^-]*\)-\(.*\)$/\3/'`
	_host_vendor=`echo $guessed_host | sed 's/^\([^-]*\)-\([^-]*\)-\(.*\)$/\2/'`
	;;
esac

#
# Determine extension used for executables
#
case $_host_os in
mingw* | cygwin* |os2-emx*)
	EXEEXT=".exe"
	;;
arm-riscos-aof)
	EXEEXT=",ff8"
	;;
psp)
	EXEEXT=".elf"
	;;
gp2x)
	EXEEXT=""
	;;
*)
	EXEEXT=""
	;;
esac

#
# Determine separator used for $PATH
#
case $_host_os in
os2-emx* )
        SEPARATOR=";"
        ;;
* )
        SEPARATOR=":"
        ;;
esac


#
# Determine the C++ compiler
#
echo_n "Looking for C++ compiler... "
if test -n "$_host"; then
	compilers="$CXX $_host_prefix-g++ $_host_prefix-c++ $_host_cpu-$_host_os-g++ $_host_cpu-$_host_os-c++"
else
	compilers="$CXX g++ c++"
fi

for compiler in $compilers; do
	if test_compiler $compiler; then
		CXX=$compiler
		echo $CXX
		break
	fi
done
if test -z $CXX; then
	echo "none found!"
	exit 1
fi

#
# Determine the compiler version

echocheck "compiler version"

cxx_name=`( $cc -v ) 2>&1 | tail -n 1 | cut -d ' ' -f 1`
cxx_version=`( $CXX -dumpversion ) 2>&1`
if test "$?" -gt 0; then
	cxx_version="not found"
fi

case $cxx_version in
	2.95.[2-9]|2.95.[2-9][-.]*|3.[0-9]|3.[0-9].[0-9]|3.[0-9].[0-9][-.]*|4.[0-9].[0-9]|4.[0-9].[0-9][-.]*)
		_cxx_major=`echo $cxx_version | cut -d '.' -f 1`
		_cxx_minor=`echo $cxx_version | cut -d '.' -f 2`
		cxx_version="$cxx_version, ok"
		cxx_verc_fail=no
		;;
	# whacky beos version strings
	2.9-beos-991026*|2.9-beos-000224*)	
		_cxx_major=2
		_cxx_minor=95
		cxx_version="$cxx_version, ok"
		cxx_verc_fail=no
		;;
	3_4)
		_cxx_major=3
		_mxx_minor=4
		;;
	'not found')
		cxx_verc_fail=yes
		;;
	*)
		cxx_version="$cxx_version, bad"
		cxx_verc_fail=yes
		;;
esac

echo "$cxx_version"

if test "$cxx_verc_fail" = yes ; then
	echo
	echo "The version of your compiler is not supported at this time"
	echo "Please ensure you are using GCC 2.95.x or GCC 3.x"
	exit 1	
fi

#
# Do CXXFLAGS now we know the compiler version
#

if test "$_cxx_major" -ge "3" ; then
	CXXFLAGS="$CXXFLAGS"
	_make_def_HAVE_GCC3='HAVE_GCC3 = 1'
fi;

if test -n "$_host"; then
	# Cross-compiling mode - add your target here if needed
	case "$_host" in
#		linupy|arm-riscos-aof)
#			echo "Cross-compiling to $_host, forcing endianness, alignment and type sizes"
#			DEFINES="$DEFINES -DUNIX"
#			_def_endianness='#define SCUMM_LITTLE_ENDIAN'
#			_def_align='#define SCUMM_NEED_ALIGNMENT'
#			_def_linupy="#define DLINUPY"
#			type_1_byte='char'
#			type_2_byte='short'
#			type_4_byte='int'
#			;;
#		ppc-amigaos)
#			echo "Cross-compiling to $_host, forcing endianness, alignment and type sizes"
#			_def_endianness='#define SCUMM_BIG_ENDIAN'
#			_def_align='#define	SCUMM_NEED_ALIGNMENT'
#			type_1_byte='char'
#			type_2_byte='short'
#			type_4_byte='long'
#			CXXFLAGS="$CFLAGS -newlib -mstrict-align -mcpu=750 -mtune=7400"
#			LDFLAGS="$LDFLAGS -newlib"
#			;;
		gp2x)
			echo "Cross-compiling to $_host, forcing static build, and disabling OpenGL."
			_build_static=yes
			_build_gl=no
			_build_windowed=no
			;;
		mingw32-cross)
			echo "Cross-compiling for Win32 using MinGW."
			DEFINES="$DEFINES -DWIN32"
			_host_os=win32
			;;
		*)
			echo "Cross-compiling to unknown target, please add your target to configure."
			exit 1
			;;
	esac
	
else
	#
	# Determine build settings
	#
	# TODO - also add an command line option to override this?!?
	echo_n "Checking hosttype... "
	echo $_host_os
	case $_host_os in
		linux* | openbsd* | freebsd* | netbsd* | bsd* | sunos* | hpux* | beos*)
			DEFINES="$DEFINES -DUNIX"
			_host_os=unix
			;;
		irix*)
			DEFINES="$DEFINES -DUNIX"
			_ranlib=:
			_host_os=unix
			;;
		mingw*)
			DEFINES="$DEFINES -DWIN32"
			_host_os=win32
			;;
		cygwin*)
			DEFINES="$DEFINES -mno-cygwin -DWIN32"
			LIBS="$LIBS -mno-cygwin -lmingw32 -lwinmm"
			_host_os=win32
			;;
		os2*)
			DEFINES="$DEFINES -DUNIX -DOS2"
			_host_os=unix
			;;
		# given this is a shell script assume some type of unix
		*)
			echo "WARNING: could not establish system type, assuming unix like"
			DEFINES="$DEFINES -DUNIX"
			;;
	esac
fi

# Cross-compilers use their own commands for the following functions
if test -n "$_host_prefix"; then
	_strip="$_host_prefix-$_strip"
	_windres="$_host_prefix-$_windres"
fi

#
# Check for ZLib
#
echocheck "zlib"
if test "$_zlib" = auto ; then
	_zlib=no
	cat > $TMPC << EOF
#include <string.h>
#include <zlib.h>
int main(void) { return strcmp(ZLIB_VERSION, zlibVersion()); }
EOF
	cc_check $LDFLAGS $CXXFLAGS $ZLIB_CFLAGS $ZLIB_LIBS -lz && _zlib=yes
fi
if test "$_zlib" = yes ; then
	echo "$_zlib"
else
	echo "none found, using built-in version"
fi

#
# Check for GL
#
echocheck "opengl"
if test "$_opengl" = auto ; then
	_opengl=no
	cat > $TMPC << EOF
#include <string.h>
#include <GL/gl.h>
#include <GL/glu.h>
int main(void) { return 0; }
EOF
	cc_check $LDFLAGS $CXXFLAGS && _opengl=yes
fi
echo "$_opengl"

#
# figure out installation directories
#
test -z "$_bindir" && _bindir="$_prefix/bin"
test -z "$_docdir" && _docdir="$_prefix/share/doc/stella"
test -z "$_datadir" && _datadir="$_prefix/share"

echo
echo_n "Summary:"
echo

if test "$_build_gl" = "yes" ; then
	if test "$_opengl" = "yes" ; then
		echo_n "   OpenGL rendering enabled"
		echo
	else
		echo_n "   OpenGL rendering disabled (missing OpenGL headers)"
		echo
		_build_gl=no
	fi
else
	echo_n "   OpenGL rendering disabled"
	echo
fi

if test "$_build_windowed" = "yes" ; then
	echo_n "   Windowed rendering modes enabled"
	echo
else
	echo_n "   Windowed rendering modes disabled"
	echo
fi

if test "$_build_sound" = "yes" ; then
	echo_n "   Sound support enabled"
	echo
else
	echo_n "   Sound support disabled"
	echo
fi

if test "$_build_debugger" = "yes" ; then
	echo_n "   Debugger support enabled"
	echo
else
	echo_n "   Debugger support disabled"
	echo
fi

if test "$_build_snapshot" = "yes" ; then
	echo_n "   Snapshot support enabled"
	echo
else
	echo_n "   Snapshot support disabled"
	echo
fi

if test "$_build_joystick" = yes ; then
	echo_n "   Joystick support enabled"
	echo
else
	echo_n "   Joystick support disabled"
	echo
fi

if test "$_build_cheats" = yes ; then
	echo_n "   Cheatcode support enabled"
	echo
else
	echo_n "   Cheatcode support disabled"
	echo
fi

if test "$_build_static" = yes ; then
	echo_n "   Static binary enabled"
	echo
else
	echo_n "   Static binary disabled"
	echo
fi

if test "$_build_profile" = yes ; then
	echo_n "   Profiling enabled"
	echo
else
	echo_n "   Profiling disabled"
	echo
fi


#
# Now, add the appropriate defines/libraries/headers
#
echo
find_sdlconfig

SRC="src"
CORE="$SRC/emucore"
COMMON="$SRC/common"
GUI="$SRC/gui"
DBG="$SRC/debugger"
DBGGUI="$SRC/debugger/gui"
YACC="$SRC/yacc"
CHEAT="$SRC/cheat"
ZLIB="$SRC/zlib"

INCLUDES="-I$CORE -I$COMMON -I$GUI"

INCLUDES="$INCLUDES `$_sdlconfig --cflags`"
if test "$_build_static" = yes ; then
	_sdl_conf_libs="--static-libs"
	LDFLAGS="-static $LDFLAGS"
else
	_sdl_conf_libs="--libs"
fi

LIBS="$LIBS `$_sdlconfig $_sdl_conf_libs`"
LD=$CXX 
case $_host_os in
		unix)
			DEFINES="$DEFINES -DBSPF_UNIX -DHAVE_GETTIMEOFDAY -DHAVE_INTTYPES"
			MODULES="$MODULES $SRC/unix"
			INCLUDES="$INCLUDES -I$SRC/unix"
			;;
		win32)
			DEFINES="$DEFINES -DBSPF_WIN32 -DHAVE_GETTIMEOFDAY -DHAVE_INTTYPES"
			MODULES="$MODULES $SRC/win32"
			INCLUDES="$INCLUDES -I$SRC/win32"
			LIBS="$LIBS -lmingw32 -lwinmm"
			;;
		gp2x)
			# -O3 hangs the GP2X, do not use.
			CXXFLAGS="-O2 -finline-functions -mtune=arm920t"
			DEFINES="$DEFINES -DBSPF_GP2X -DGP2X -DHAVE_GETTIMEOFDAY -DHAVE_INTTYPES"
			MODULES="$MODULES $SRC/gp2x"
			INCLUDES="$INCLUDES -I$SRC/gp2x $ZLIB_CFLAGS"
			
			_ranlib="arm-linux-ranlib"
			_ar="arm-linux-ar cru"
			;;
		*)
			echo "WARNING: host system not currenty supported"
			exit
			;;
esac

if test "$_zlib" = yes ; then
  LIBS="$LIBS -lz"
else
	MODULES="$MODULES $ZLIB"
	INCLUDES="$INCLUDES -I$ZLIB"
fi

if test "$_build_gl" = yes ; then
	DEFINES="$DEFINES -DDISPLAY_OPENGL"
fi

if test "$_build_windowed" = yes ; then
	DEFINES="$DEFINES -DWINDOWED_SUPPORT"
fi

if test "$_build_sound" = yes ; then
	DEFINES="$DEFINES -DSOUND_SUPPORT"
fi

if test "$_build_debugger" = yes ; then
	DEFINES="$DEFINES -DDEBUGGER_SUPPORT"
	MODULES="$MODULES $DBG $DBGGUI $YACC"
	INCLUDES="$INCLUDES -I$DBG -I$DBGGUI -I$YACC"
fi

if test "$_build_snapshot" = yes ; then
	DEFINES="$DEFINES -DSNAPSHOT_SUPPORT"
fi

if test "$_build_joystick" = yes ; then
	DEFINES="$DEFINES -DJOYSTICK_SUPPORT"
fi

if test "$_build_cheats" = yes ; then
	DEFINES="$DEFINES -DCHEATCODE_SUPPORT"
	MODULES="$MODULES $CHEAT"
	INCLUDES="$INCLUDES -I$CHEAT"
fi

if test "$_build_profile" = no ; then
	_build_profile=
fi


echo "Creating config.mak"
cat > config.mak << EOF
# -------- Generated by configure -----------

CXX := $CXX
CXXFLAGS := $CXXFLAGS
LD := $LD
LIBS += $LIBS
RANLIB := $_ranlib
INSTALL := $_install
AR := $_ar
MKDIR := $_mkdir
ECHO := $_echo
CAT := $_cat
RM := $_rm
RM_REC := $_rm_rec
ZIP := $_zip
CP := $_cp
WIN32PATH=$_win32path
STRIP := $_strip
WINDRES := $_windres

MODULES += $MODULES
MODULE_DIRS += $MODULE_DIRS
EXEEXT := $EXEEXT

PREFIX := $_prefix
BINDIR := $_bindir
DOCDIR := $_docdir
DATADIR := $_datadir
PROFILE := $_build_profile

$_make_def_HAVE_GCC3

INCLUDES += $INCLUDES
OBJS += $OBJS
DEFINES += $DEFINES
LDFLAGS += $LDFLAGS
EOF

# This should be taken care of elsewhere, but I'm not sure where
rm -f stella-conf*
