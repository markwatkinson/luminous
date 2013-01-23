#!/bin/sh
# Quick and dirty script to download and install various
# redistributable runtime libraries
#
# Current maintainers: Austin English, Dan Kegel
# Copyright 2007, 2008, 2009 Google (Dan Kegel, dank@kegel.com)
# Copyright 2008, 2009 Austin English (austinenglish@gmail.com)
# Thanks to Detlef Riekenberg for lots of updates
# Thanks to Saulius Krasuckas for corrections and suggestions
# Thanks to Erik Inge Bolsø for several patches
# Thanks to Hugh Perkins for the directplay patch
# Please report problems at http://code.google.com/p/winezeug/issues
# See also http://wiki.winehq.org/winetricks
#
# Note to contributors: please avoid gnu extensions in this shell script,
# as it has to run on MacOSX and Solaris, too.  A good book on the topic is
# "Portable Shell Programming" by Bruce Blinn

#---- Constants -------------------------------------------------

# Name of this version of winetricks (YYYYMMDD)
VERSION=20091213

early_wine()
{
    WINEDLLOVERRIDES=mshtml= $WINE "$@"
}

# Default values for important settings if not already in environment.
# These settings should not need editing here.
case "$OS" in
 "Windows_NT")
    # Cheezy fix for getting rid of double slashes when running cygwin in wine
    case $HOME in
      /) HOME="" ;;
    esac
    WINE=""
    WINEPREFIX=${WINEPREFIX:-$HOME/.wine}
    DRIVE_C="C:/"
    XXXPATH=cygpath
    ;;
 *)
    WINE=${WINE:-wine}
    WINEPREFIX=${WINEPREFIX:-$HOME/.wine}
    DRIVE_C="$WINEPREFIX/drive_c"
    XXXPATH="early_wine winepath"
    ;;
esac

# Internal variables; these locations are not too important
WINETRICKS_CACHE=$HOME/winetrickscache
# Default to hiding the directory, by popular demand
test -d "$WINETRICKS_CACHE" || WINETRICKS_CACHE=$HOME/.winetrickscache
WINETRICKS_CACHE_WIN="`$XXXPATH -w $WINETRICKS_CACHE`"
WINETRICKS_TMP="$DRIVE_C"/winetrickstmp
WINETRICKS_TMP_WIN='c:\winetrickstmp'
mkdir -p $WINETRICKS_TMP

# Overridden for windows
ISO_MOUNT_ROOT=/mnt/winetricks

WINDIR="$DRIVE_C/windows"

# Which sourceforge mirror to use.  Rotate based on time, since
# their mirror picker sometimes persistantly sends you to a broken
# mirror.
case `date +%S` in
*[01]) SOURCEFORGE=http://internap.dl.sourceforge.net/sourceforge ;;
*[23]) SOURCEFORGE=http://easynews.dl.sourceforge.net/sourceforge ;;
*)     SOURCEFORGE=http://downloads.sourceforge.net;;
esac

#---- Functions -------------------------------------------------

# Check for known desktop environments
# Set variable DE to the desktop environment's name, lowercase
detectDE() {
    if [ x"$KDE_FULL_SESSION" = x"true" ]
    then
        DE=kde
    elif [ x"$GNOME_DESKTOP_SESSION_ID" != x"" ]
    then
        DE=gnome
    elif [ x"$DISPLAY" != x"" ]
    then
        DE=x
    else
        DE=none
    fi
}

warn() {
    echo "$@"

    test "$GUI" = 1 || return

    case $DE in
    gnome) zenity --error --title=Winetricks --text="$@" --no-wrap ;;
    kde) kdialog --title Winetricks --error "$@" ;;
    x) xmessage -title Winetricks -center "  Error: $@  " ;;
    esac
}

die() {
    warn "$@"

    exit 1
}

# Abort if user doesn't own the given directory (or its parent, if it doesn't exist yet)
die_if_user_not_dirowner() {
    if test -d "$1"
    then
        checkdir="$1"
    else
        # fixme: quoting problem?
        checkdir=`dirname "$1"`
    fi
    nuser=`id -u`
    nowner=`ls -l -n -d "$checkdir" | awk '{print $3}'`
    if test x$nuser != x$nowner
    then
        die "You (`id -un`) don't own $checkdir. Don't run winetricks as another user!"
    fi
}

#----------------------------------------------------------------

usage() {
    set +x
    # WARNING: do not use single quote in any package description; that breaks the gui menu.
    echo "Usage: $0 [options] package [package] ..."
    echo "This script can help you prepare your system for Windows applications"
    echo "that mistakenly assume all users' systems have all the needed"
    echo "redistributable runtime libraries or fonts."
    echo "Some options require the Linux 'cabextract' program."
    echo ""
    echo "Options:"
    echo " -q         quiet.  You must have already agreed to the EULAs."
    echo " -v         verbose"
    echo " -V         display Version"
    echo "Packages:"
    echo " art2kmin      MS Access 2007 runtime"
    echo " atmlib        Adobe Type Manager. Needed for Adobe CS4"
    echo " autohotkey    Autohotkey (open source gui scripting language)"
    echo " cmake         CMake, the cross-platform, open-source build system"
    echo " colorprofile  Standard RGB color profile"
    echo " comctl32      MS common controls 5.80"
    echo " comctl32.ocx  MS comctl32.ocx and mscomctl.ocx, comctl32 wrappers for VB6"
    echo " controlpad    MS ActiveX Control Pad"
    echo " corefonts     MS Arial, Courier, Times fonts"
    echo " cygwin        Unix apps for Windows (needed by some build scripts)"
    echo " d3dx9         MS d3dx9_??.dll (from DirectX 9 user redistributable)"
    echo " dcom98        MS DCOM (ole32, oleaut32); requires Windows 98 license, but does not check for one"
    echo " dinput8       MS dinput8.dll (from DirectX 9 user redistributable)"
    echo " dirac0.8      the obsolete Dirac 0.8 directshow filter"
    echo " directplay    MS DirectPlay (from DirectX 9 user redistributable)"
    echo " directx9      MS DirectX 9 user redistributable (not recommended! use d3dx9 instead)"
    echo " divx          divx video codec"
    echo " dotnet11      MS .NET 1.1 (requires Windows license, but does not check for one)"
# Doesn't work yet, don't make it public
#    echo " dotnet11sdk   MS .NET Framework SDK Version 1.1 (requires Windows license, but does not check for one; may not work yet)"
    echo " dotnet20      MS .NET 2.0 (requires Windows license, but does not check for one)"
# Doesn't work yet, don't make it public
#    echo " dotnet20sdk   MS .NET Framework SDK Version 2.0 (requires Windows license, but does not check for one, may not work yet)"
    echo " dotnet20sp2   MS .NET 2.0 sp2 (requires Windows license, but does not check for one)"
    echo " dotnet30      MS .NET 3.0 (requires Windows license, but does not check for one, might not work yet)"
    echo " droid         Droid fonts (on LCD, looks better with fontsmooth-rgb)"
    echo " ffdshow       ffdshow video codecs"
    echo " firefox       Firefox web browser"
    echo " flash         Adobe Flash Player ActiveX and firefox plugins"
    echo " fm20          MS Forms 2.0 Object Library"
    echo " fontfix       Fix bad fonts which cause crash in some apps (e.g. .net)."
    echo " fontsmooth-bgr        Enables subpixel smoothing for BGR LCDs"
    echo " fontsmooth-disable    Disables font smoothing"
    echo " fontsmooth-gray       Enables grayscale font smoothing"
    echo " fontsmooth-rgb        Enables subpixel smoothing for RGB LCDs"
    echo " gdiplus       MS gdiplus.dll"
    echo " gecko-dbg     The HTML rendering Engine (Mozilla), with debugging symbols"
    echo " gecko         The HTML rendering Engine (Mozilla)"
    echo " hosts         Adds empty C:\windows\system32\drivers\etc\{hosts,services} files"
    echo " icodecs       Intel Codecs (Indeo)"
    echo " ie6           Microsoft Internet Explorer 6.0"
    echo " ie7           Microsoft Internet Explorer 7.0"
    echo " jet40         MS Jet 4.0 Service Pack 8"
    echo " kde           KDE for Windows installer"
    echo " liberation    Red Hat Liberation fonts (Sans, Serif, Mono)"
    echo " mdac25        MS MDAC 2.5: Microsoft ODBC drivers, etc."
    echo " mdac27        MS MDAC 2.7"
    echo " mdac28        MS MDAC 2.8"
    echo " mfc40         MS mfc40 (Microsoft Foundation Classes from Visual C++ 4)"
    echo " mfc42         MS mfc42 (same as vcrun6 below)"
    echo " mingw-gdb     GDB for MinGW"
    echo " mingw         Minimalist GNU for Windows, including GCC for Windows!"
    echo " mono20        mono-2.0.1"
    echo " mono22        mono-2.2"
    echo " mono24        mono-2.4"
    echo " mozillabuild  Mozilla build environment"
    echo " mpc           Media Player Classic"
    echo " mshflxgd      MS Hierarchical Flex Grid Control"
    echo " msi2          MS Installer 2.0"
    echo " msls31        MS Line Services 3.1 (needed by native riched?)"
    echo " msmask        MS Masked Edit Control"
    echo " msscript      MS Script Control"
    echo " msxml3        MS XML version 3"
    echo " msxml4        MS XML version 4"
    echo " msxml6        MS XML version 6"
    echo " ogg           ogg filters/codecs: flac, theora, speex, vorbis, schroedinger"
    echo " ole2          MS 16 bit OLE"
    echo " openwatcom    Open Watcom C/C++ compiler (can compile win16 code!)"
    echo " pdh           MS pdh.dll (Performance Data Helper)"
    echo " physx         NVIDIA/AGEIA PhysX runtime"
    echo " psdk2003      MS Platform SDK 2003"
    echo " psdkvista     MS Vista SDK (does not install yet)"
    echo " psdkwin7      MS Windows 7 SDK (installing just headers and c++ compiler works)"
    echo " python26      Python 2.6.2 (and pywin32)"
    echo " python-comtypes Python 0.6.1-1 comtypes package"
    echo " quicktime72   Apple Quicktime 7.2"
    echo " riched20      MS riched20 and riched32"
    echo " riched30      MS riched30"
    echo " richtx32      MS Rich TextBox Control 6.0"
    echo " shockwave     Adobe Shockwave Player"
    echo " tahoma        MS Tahoma font (not part of corefonts)"
    echo " urlmon        MS urlmon.dll"
    echo " vb2run        MS Visual Basic 2 runtime"
    echo " vb3run        MS Visual Basic 3 runtime"
    echo " vb4run        MS Visual Basic 4 runtime"
    echo " vb5run        MS Visual Basic 5 runtime"
    echo " vb6run        MS Visual Basic 6 Service Pack 6 runtime"
    echo " vc2005express MS Visual C++ 2005 Express"
    echo " vc2005expresssp1 MS Visual C++ 2005 Express SP1 (does not work yet)"
    echo " vc2005sp1     MS Visual C++ 2005 Service Pack 1 (install trial 1st)"
    echo " vc2005trial   MS Visual C++ 2005 Trial"
    echo " vcrun2003     MS Visual C++ 2003 libraries (mfc71,msvcp71,msvcr71)"
    echo " vcrun2005     MS Visual C++ 2005 sp1 libraries (mfc80,msvcp80,msvcr80)"
    echo " vcrun2008     MS Visual C++ 2008 libraries (mfc90,msvcp90,msvcr90)"
    echo " vcrun6        MS Visual C++ 6 sp4 libraries (mfc42, msvcp60, msvcrt)"
    echo " vcrun6sp6     MS Visual C++ 6 sp6 libraries (mfc42, msvcp60, msvcrt; 64 MB download)"
    echo " vjrun20       MS Visual J# 2.0 SE libraries (requires dotnet20)"
    echo " vlc           VLC media player"
    echo " wenquanyi     WenQuanYi CJK font (on LCD looks better with fontsmooth-rgb)"
    echo " wininet       MS wininet.dll (requires Windows license, but does not check for one)"
    echo " wme9          MS Windows Media Encoder 9 (requires Windows license, but does not check for one)"
    echo " wmp10         MS Windows Media Player 10 (requires Windows license, but does not check for one)"
    echo " wmp9          MS Windows Media Player 9 (requires Windows license, but does not check for one)"
    echo " wsh56js       MS Windows scripting 5.6, jscript only, no cscript"
    echo " wsh56         MS Windows Scripting Host 5.6"
    echo " wsh56vb       MS Windows scripting 5.6, vbscript only, no cscript"
    echo " xact          MS XACT Engine (x3daudio?_?.dll, xactengine?_?.dll)"
    echo " xvid          xvid video codec"
    echo "Pseudopackages:"
    echo " allfonts      All listed fonts (corefonts, tahoma, liberation)"
    echo " allcodecs     All listed codecs (xvid, ffdshow, icodecs)"
    echo " fakeie6       Set registry to claim IE6sp1 is installed"
    echo " glsl-disable  Disable GLSL use by Wine Direct3D"
    echo " glsl-enable   Enable GLSL use by Wine Direct3D (default)"
    echo " heapcheck     Enable heap checking"
    echo " native_mdac   Override odbc32, odbccp32 and oledb32"
    echo " native_oleaut32 Override oleaut32"
    echo " nocrashdialog Disable the graphical crash dialog"
    echo " orm=backbuffer Registry tweak: OffscreenRenderingMode=backbuffer"
    echo " orm=fbo        Registry tweak: OffscreenRenderingMode=fbo (default)"
    echo " orm=pbuffer    Registry tweak: OffscreenRenderingMode=pbuffer"
    echo " sandbox       Sandbox the wineprefix - remove links to ~"
    echo " sound=alsa       Set sound driver to ALSA"
    echo " sound=audioio    Set sound driver to AudioIO"
    echo " sound=coreaudio  Set sound driver to CoreAudio"
    echo " sound=esound     Set sound driver to Esound"
    echo " sound=jack       Set sound driver to Jack"
    echo " sound=nas        Set sound driver to Nas"
    echo " sound=oss        Set sound driver to OSS"
    echo " nt40          Set windows version to nt40"
    echo " win98         Set windows version to Windows 98"
    echo " win2k         Set windows version to Windows 2000"
    echo " winxp         Set windows version to Windows XP"
    echo " vista         Set windows version to Windows Vista"
    echo " winver=       Set windows version to default (winxp)"
    echo " volnum        Rename drive_c to harddiskvolume0 (needed by some installers)"
}

#----------------------------------------------------------------
# Trivial GUI just to handle case where user tries running without commandline

kde_showmenu() {
    title="$1"
    shift
    text="$1"
    shift
    col1name="$1"
    shift
    col2name="$1"
    shift
    while test $# -gt 0
    do
        args="$args $1 $1 off"
        shift
    done
    kdialog --title "$title" --separate-output --checklist "$text" $args
}

x_showmenu() {
    title="$1"
    shift
    text="$1"
    shift
    col1name="$1"
    shift
    col2name="$1"
    shift
    if test $# -gt 0
    then
        args="$1"
        shift
    fi
    while test $# -gt 0
    do
        args="$args,$1"
        shift
    done
    (echo "$title"; echo ""; echo "$text") | \
    xmessage -print -file - -buttons "Cancel,$args" | sed 's/Cancel//'
}

showmenu()
{
    case $DE in
    kde) kde_showmenu "$@" ;;
    gnome|x) x_showmenu "$@" ;;
    none) usage 1>&2; exit 1;;
    esac
}

dogui()
{
    if [ $DE = gnome ]
    then
        echo "zenity --title 'Select a package to install' --text 'Install?' --list --checklist --column '' --column Package --column Description --height 440 --width 600 \\" > "$WINETRICKS_TMP"/zenity.sh
        usage | grep '^ [a-z]' | sed 's/^ \([^ ]*\) *\(.*\)/FALSE "\1" '"'\2'/" | sed 's/$/ \\/' >> $WINETRICKS_TMP/zenity.sh
        todo="`sh "$WINETRICKS_TMP"/zenity.sh | tr '|' ' '`"
    else
        packages=`usage | awk '/^ [a-z]/ {print $1}'`
        todo="`showmenu "winetricks" "Select a package to install" "Install?" "Package" $packages`"
    fi

    if test "$todo"x = x
    then
       exit 0
    fi
}

#-----  Helpers  ------------------------------------------------

# Execute with error checking
try() {
    # "VAR=foo try cmd" fails to put VAR in the environment
    # with some versions of bash if try is a shell function?!
    # Adding this explicit export works around it.
    export WINEDLLOVERRIDES
    echo Executing "$@"
    # Mark executable - needed if running on windows vista
    case "$1" in
    *.exe) chmod +x "$1" || true
      cmd /c "$@"
      ;;
    *)
      "$@"
      ;;
    esac
    status=$?
    if test $status -ne 0
    then
        die "Note: command '$@' returned status $status.  Aborting."
    fi
}

try_regedit() {
    try early_wine regedit "$@"
}

regedit() {
    die oops, bug, please report
}

# verify an sha1sum
verify_sha1sum() {
    wantsum=$1
    file=$2

    gotsum=`$SHA1SUM < $file | sed 's/ .*//'`
    if [ "$gotsum"x != "$wantsum"x ]
    then
       die "sha1sum mismatch!  Rename $file and try again."
    fi
}

# Download a file
# Usage: package url [sha1sum [filename]]
# Caches downloads in winetrickscache/$package
download() {
    if [ "$4"x != ""x ]
    then
        file="$4"
    else
        file=`basename "$2"`
    fi
    cache="$WINETRICKS_CACHE/$1"
    mkdir -p "$cache"
    if test ! -f "$cache/$file"
    then
        cd "$cache"
        # Mac folks tend to have curl rather than wget
        # On Mac, 'which' doesn't return good exit status
        # Need to jam in --header "Accept-Encoding: gzip,deflate" else
        # redhat.com decompresses liberation-fonts.tar.gz!
        if [ -x "`which wget`" ]
        then
           # Use -nd to insulate ourselves from people who set -x in WGETRC
           # [*] --retry-connrefused works around the broken sf.net mirroring
           # system when downloading corefonts
           # [*] --read-timeout is useful on the adobe server that doesn't
           # close the connection unless you tell it to (control-C or closing
           # the socket)
           try wget -O "$file" -nd -c --read-timeout=300 --retry-connrefused --header "Accept-Encoding: gzip,deflate" "$2"
        else
           # curl doesn't get filename from the location given by the server!
           # fortunately, we know it
           try curl -L -o "$file" -C - --header "Accept-Encoding: gzip,deflate" "$2"
        fi
        # Need to decompress .exe's that are compressed, else cygwin fails
        # Only affects cygwin, so don't barf if 'file' not installed
        FILE=`which file`
        case $FILE-$file in
        /*-*.exe)
            case `file $file` in
            *gzip*) mv $file $file.gz; gunzip < $file.gz > $file;;
            esac
        esac
           
        cd "$olddir"
    fi
    if [ "$3"x != ""x ]
    then
        verify_sha1sum $3  "$cache/$file"
    fi
}

set_winver() {
    echo "Setting Windows version to $1"
    cat > "$WINETRICKS_TMP"/set-winver.reg <<_EOF_
REGEDIT4

[HKEY_CURRENT_USER\Software\Wine]
"Version"="$1"

_EOF_
    try_regedit "$WINETRICKS_TMP"/set-winver.reg
}

set_app_winver() {
    app="$1"
    version="$2"
    echo "Setting $app to $version mode"
    (
    echo REGEDIT4
    echo ""
    echo "[HKEY_CURRENT_USER\\Software\\Wine\\AppDefaults\\$app]"
    echo "\"Version\"=\"$version\""
    ) > "$WINETRICKS_TMP"/set-winver.reg

    try_regedit "$WINETRICKS_TMP"/set-winver.reg
    rm "$WINETRICKS_TMP"/set-winver.reg
}

set_orm() {
    echo "Setting OffscreenRenderingMode to $1"
    cat > "$WINETRICKS_TMP"/set-orm.reg <<_EOF_
REGEDIT4

[HKEY_CURRENT_USER\Software\Wine\Direct3D]
"OffscreenRenderingMode"="$1"

_EOF_
    try_regedit "$WINETRICKS_TMP"/set-orm.reg
}

set_sound_driver() {
    echo "Setting sound driver to to $1"
    cat > "$WINETRICKS_TMP"/set-sound.reg <<_EOF_
REGEDIT4

[HKEY_CURRENT_USER\Software\Wine\Drivers]
"Audio"="$1"

_EOF_
    try_regedit "$WINETRICKS_TMP"/set-sound.reg
}

disable_crashdialog() {
    echo "Disabling graphical crash dialog"
    cat > "$WINETRICKS_TMP"/crashdialog.reg <<_EOF_
REGEDIT4

[HKEY_CURRENT_USER\Software\Wine\WineDbg]
"ShowCrashDialog"=dword:00000000

_EOF_
    try_regedit "$WINETRICKS_TMP"/crashdialog.reg
}

sandbox() {
    # remove symlinks
    try rm "$WINEPREFIX/drive_c/users/$USER/Desktop"
    try rm "$WINEPREFIX/drive_c/users/$USER/My Documents"
    try rm "$WINEPREFIX/drive_c/users/$USER/My Music"
    try rm "$WINEPREFIX/drive_c/users/$USER/My Pictures"
    try rm "$WINEPREFIX/drive_c/users/$USER/My Videos"
    # create replacement directories
    try mkdir "$WINEPREFIX/drive_c/users/$USER/Desktop"
    try mkdir "$WINEPREFIX/drive_c/users/$USER/My Documents"
    try mkdir "$WINEPREFIX/drive_c/users/$USER/My Music"
    try mkdir "$WINEPREFIX/drive_c/users/$USER/My Pictures"
    try mkdir "$WINEPREFIX/drive_c/users/$USER/My Videos"
}

unset_winver() {
    echo "Clearing Windows version back to default"
    cat > "$WINETRICKS_TMP"/unset-winver.reg <<_EOF_
REGEDIT4

[HKEY_CURRENT_USER\Software\Wine]
"Version"=-

_EOF_
    try_regedit "$WINETRICKS_TMP"/unset-winver.reg
}

override_dlls() {
    mode=$1
    shift
    echo Using $mode override for following DLLs: $@
    cat > "$WINETRICKS_TMP"/override-dll.reg <<_EOF_
REGEDIT4

[HKEY_CURRENT_USER\Software\Wine\DllOverrides]
_EOF_
    while test "$1" != ""
    do
        case "$1" in
        comctl32)
           rm -rf "$WINDIR"/winsxs/manifests/x86_microsoft.windows.common-controls_6595b64144ccf1df_6.0.2600.2982_none_deadbeef.manifest
           ;;
        esac
        echo "\"$1\"=\"$mode\"" >> "$WINETRICKS_TMP"/override-dll.reg
        shift
    done

    try_regedit "$WINETRICKS_TMP"/override-dll.reg
    rm "$WINETRICKS_TMP"/override-dll.reg
}

override_app_dlls() {
    app=$1
    shift
    mode=$1
    shift
    echo Using $mode override for following DLLs when running $app: $@
    (
    echo REGEDIT4
    echo ""
    echo "[HKEY_CURRENT_USER\\Software\\Wine\\AppDefaults\\$app\\DllOverrides]"
    ) > "$WINETRICKS_TMP"/override-dll.reg

    while test "$1" != ""
    do
        case "$1" in
        comctl32)
           rm -rf "$WINDIR"/winsxs/manifests/x86_microsoft.windows.common-controls_6595b64144ccf1df_6.0.2600.2982_none_deadbeef.manifest
           ;;
        esac
        echo "\"$1\"=\"$mode\"" >> "$WINETRICKS_TMP"/override-dll.reg
        shift
    done

    try_regedit "$WINETRICKS_TMP"/override-dll.reg
    rm "$WINETRICKS_TMP"/override-dll.reg
}

register_font() {
    file=$1
    shift
    font=$1
    #echo "Registering $file as $font"
    cat > "$WINETRICKS_TMP"/register-font.reg <<_EOF_
REGEDIT4

[HKEY_LOCAL_MACHINE\Software\Microsoft\Windows NT\CurrentVersion\Fonts]
"$font"="$file"
_EOF_
    # too verbose
    try_regedit "$WINETRICKS_TMP"/register-font.reg
}

append_path() {
# Get the %PATH%
WIN_PATH="`WINEDEBUG= $WINE cmd.exe /c echo "%PATH%"`"

# Set the path. Make sure to not remove any other variables in %PATH% set by the user/other programs:
    NEW_PATH="$1"
    cat > "$WINETRICKS_TMP"/path.reg <<_EOF_
REGEDIT4

[HKEY_LOCAL_MACHINE\\System\\CurrentControlSet\\Control\\Session Manager\\Environment]
_EOF_

    printf '"PATH"="' >>"$WINETRICKS_TMP"/path.reg
    printf "$NEW_PATH;$WIN_PATH"'\"' | sed "s/\\\\/\\\\\\\\/g" >> "$WINETRICKS_TMP"/path.reg

    try_regedit "$WINETRICKS_TMP"/path.reg
    rm -f "$WINETRICKS_TMP"/path.reg

}
#----- common download for several verbs
helper_directx_dl() {
    # Aug 2009 DirectX 9c User Redistributable
    # http://www.microsoft.com/downloads/details.aspx?displaylang=en&FamilyID=04ac064b-00d1-474e-b7b1-442d8712d553
    download . http://download.microsoft.com/download/B/7/9/B79FC9D7-47B8-48B7-A75E-101DEBEB5AB4/directx_aug2009_redist.exe 563b96a3d78d6038d10428f23954f083320b4019

    DIRECTX_NAME=directx_aug2009_redist.exe
}

#----- One function per package, in alphabetical order ----------

load_art2kmin() {
    # See http://www.microsoft.com/downloads/details.aspx?familyid=d9ae78d9-9dc6-4b38-9fa6-2c745a175aed&displaylang=en
    download . http://download.microsoft.com/download/D/2/A/D2A2FC8B-0447-491C-A5EF-E8AA3A74FB98/AccessRuntime.exe 571811b7536e97cf4e4e53bbf8260cddd69f9b2d
    cd "$WINETRICKS_CACHE"
    try $WINE AccessRuntime.exe $WINETRICKS_QUIET
    cd "$olddir"
}

#----------------------------------------------------------------

load_atmlib() {
    # http://www.microsoft.com/downloads/details.aspx?FamilyID=1001AAF1-749F-49F4-8010-297BD6CA33A0&displaylang=en
    # FIXME: This is a huge download for a single dll.
    download . http://download.microsoft.com/download/E/6/A/E6A04295-D2A8-40D0-A0C5-241BFECD095E/W2KSP4_EN.EXE fadea6d94a014b039839fecc6e6a11c20afa4fa8
    cd "$WINETRICKS_TMP"
    try cabextract "$WINETRICKS_CACHE"/W2KSP4_EN.EXE i386/atmlib.dl_
    try cp atmlib.dll "$WINDIR"/system32
    try rm -rf i386
    cd "$olddir"
}

#----------------------------------------------------------------

load_autohotkey() {
    download . http://www.autohotkey.net/programs/AutoHotkey104805_Install.exe 13e5a9ca6d5b7705f1cd02560c3af4d38b1904fc
    try $WINE "$WINETRICKS_CACHE"/AutoHotkey104805_Install.exe $WINETRICKS_S
}

#----------------------------------------------------------------

load_cc580() {
    # http://www.microsoft.com/downloads/details.aspx?familyid=6f94d31a-d1e0-4658-a566-93af0d8d4a1e
    download . http://download.microsoft.com/download/platformsdk/redist/5.80.2614.3600/w9xnt4/en-us/cc32inst.exe 94c3c494258cc54bd65d2f0153815737644bffde

    try $WINE "$WINETRICKS_CACHE"/cc32inst.exe "/T:$winetricks_tmp_win" /c $WINETRICKS_QUIET
    try unzip -o $WINETRICKS_UNIXQUIET -d "$WINDIR"/temp "$WINETRICKS_TMP"/comctl32.exe
    try $WINE "$WINDIR"/temp/x86/50ComUpd.Exe "/T:$winetricks_tmp_win" /c $WINETRICKS_QUIET
    try cp "$WINETRICKS_TMP"/comcnt.dll "$WINDIR"/system32/comctl32.dll

    override_dlls native,builtin comctl32

    # some builtin apps don't like native comctl32
    override_app_dlls winecfg.exe builtin comctl32
    override_app_dlls explorer.exe builtin comctl32
    override_app_dlls iexplore.exe builtin comctl32
}

#----------------------------------------------------------------

load_cmake() {
    download . http://www.cmake.org/files/v2.6/cmake-2.6.4-win32-x86.exe 00bd502423546b8bce19ffc180ea78e0e2f396cf
    try $WINE "$WINETRICKS_CACHE"/cmake-2.6.4-win32-x86.exe
}

#----------------------------------------------------------------

load_comctl32ocx() {
    # http://www.microsoft.com/downloads/details.aspx?FamilyID=25437D98-51D0-41C1-BB14-64662F5F62FE
    download . http://download.microsoft.com/download/3/a/5/3a5925ac-e779-4b1c-bb01-af67dc2f96fc/VisualBasic6-KB896559-v1-ENU.exe f52cf2034488235b37a1da837d1c40eb2a1bad84

    try cabextract -q --directory="$WINETRICKS_TMP" "$WINETRICKS_CACHE"/VisualBasic6-KB896559-v1-ENU.exe
    try cp "$WINETRICKS_TMP"/mscomctl.ocx "$WINDIR"/system32/mscomctl.ocx
    try cp "$WINETRICKS_TMP"/comctl32.ocx "$WINDIR"/system32/comctl32.ocx
    try $WINE regsvr32 comctl32.ocx
    try $WINE regsvr32 mscomctl.ocx
}

#----------------------------------------------------------------

load_colorprofile() {
    download . http://download.microsoft.com/download/whistler/hwdev1/1.0/wxp/en-us/ColorProfile.exe 6b72836b32b343c82d0760dff5cb51c2f47170eb
    try unzip -o $WINETRICKS_UNIXQUIET -d "$WINETRICKS_TMP" "$WINETRICKS_CACHE"/ColorProfile.exe
    mkdir -p "$WINDIR"/system32/spool/drivers/color
    try cp -f "$WINETRICKS_TMP/sRGB Color Space Profile.icm" "$WINDIR"/system32/spool/drivers/color
}

#----------------------------------------------------------------

load_controlpad() {
    # http://msdn.microsoft.com/en-us/library/ms968493.aspx
    # Fixes error "Failed to load UniText..."
    load_wsh56
    download . http://download.microsoft.com/download/activexcontrolpad/install/4.0.0.950/win98mexp/en-us/setuppad.exe 8921e0f52507ca6a373c94d222777c750fb48af7
    try cabextract --directory="$WINETRICKS_TMP" "$WINETRICKS_CACHE"/setuppad.exe
    echo "If setup says 'Unable to start DDE ...', press Ignore"
    try $WINE "$WINETRICKS_TMP"/setup $WINETRICKS_QUIET_T
}

#----------------------------------------------------------------

load_corefonts() {
    # See http://corefonts.sf.net
    # TODO: let user pick mirror,
    # see http://corefonts.sourceforge.net/msttcorefonts-2.0-1.spec for how
    # TODO: add more fonts

    # Added More Fonts (see msttcorefonts)
    # [*] Pointed download locations to sites that actually contained the
    # fonts to download (as of 04-03-2008)
    #download . $SOURCEFORGE/corefonts/andale32.exe c4db8cbe42c566d12468f5fdad38c43721844c69
    download . $SOURCEFORGE/corefonts/arial32.exe 6d75f8436f39ab2da5c31ce651b7443b4ad2916e
    download . $SOURCEFORGE/corefonts/arialb32.exe d45cdab84b7f4c1efd6d1b369f50ed0390e3d344
    download . $SOURCEFORGE/corefonts/comic32.exe 2371d0327683dcc5ec1684fe7c275a8de1ef9a51
    download . $SOURCEFORGE/corefonts/courie32.exe 06a745023c034f88b4135f5e294fece1a3c1b057
    download . $SOURCEFORGE/corefonts/georgi32.exe 90e4070cb356f1d811acb943080bf97e419a8f1e
    download . $SOURCEFORGE/corefonts/impact32.exe 86b34d650cfbbe5d3512d49d2545f7509a55aad2
    download . $SOURCEFORGE/corefonts/times32.exe 20b79e65cdef4e2d7195f84da202499e3aa83060
    download . $SOURCEFORGE/corefonts/trebuc32.exe 50aab0988423efcc9cf21fac7d64d534d6d0a34a
    download . $SOURCEFORGE/corefonts/verdan32.exe f5b93cedf500edc67502f116578123618c64a42a
    download . $SOURCEFORGE/corefonts/webdin32.exe 2fb4a42c53e50bc70707a7b3c57baf62ba58398f

    # Natively installed versions of these fonts will cause the installers
    # to exit silently. Because there are apps out there that depend on the
    # files being present in the Windows font directory we use cabextract
    # to obtain the files and register the fonts by hand.

    # Andale needs a FontSubstitutes entry
    # try cabextract --directory="$WINETRICKS_TMP" "$WINETRICKS_CACHE"/andale32.exe

    # Display EULA
    test x"$WINETRICKS_QUIET" = x"" || try $WINE "$WINETRICKS_CACHE"/arial32.exe $WINETRICKS_QUIET

    try cabextract -q --directory="$WINETRICKS_TMP" "$WINETRICKS_CACHE"/arial32.exe
    try cp -f "$WINETRICKS_TMP"/Arial*.TTF "$winefontsdir"
    register_font Arial.TTF "Arial (TrueType)"
    register_font Arialbd.TTF "Arial Bold (TrueType)"
    register_font Arialbi.TTF "Arial Bold Italic (TrueType)"
    register_font Ariali.TTF "Arial Italic (TrueType)"

    try cabextract -q --directory="$WINETRICKS_TMP" "$WINETRICKS_CACHE"/arialb32.exe
    try cp -f "$WINETRICKS_TMP"/AriBlk.TTF "$winefontsdir"
    register_font AriBlk.TTF "Arial Black (TrueType)"

    try cabextract -q --directory="$WINETRICKS_TMP" "$WINETRICKS_CACHE"/comic32.exe
    try cp -f "$WINETRICKS_TMP"/Comic*.TTF "$winefontsdir"
    register_font Comic.TTF "Comic Sans MS (TrueType)"
    register_font Comicbd.TTF "Comic Sans MS Bold (TrueType)"

    try cabextract -q --directory="$WINETRICKS_TMP" "$WINETRICKS_CACHE"/courie32.exe
    try cp -f "$WINETRICKS_TMP"/cour*.ttf "$winefontsdir"
    register_font Cour.TTF "Courier New (TrueType)"
    register_font CourBD.TTF "Courier New Bold (TrueType)"
    register_font CourBI.TTF "Courier New Bold Italic (TrueType)"
    register_font Couri.TTF "Courier New Italic (TrueType)"

    try cabextract -q --directory="$WINETRICKS_TMP" "$WINETRICKS_CACHE"/georgi32.exe
    try cp -f "$WINETRICKS_TMP"/Georgia*.TTF "$winefontsdir"
    register_font Georgia.TTF "Georgia (TrueType)"
    register_font Georgiab.TTF "Georgia Bold (TrueType)"
    register_font Georgiaz.TTF "Georgia Bold Italic (TrueType)"
    register_font Georgiai.TTF "Georgia Italic (TrueType)"

    try cabextract -q --directory="$WINETRICKS_TMP" "$WINETRICKS_CACHE"/impact32.exe
    try cp -f "$WINETRICKS_TMP"/Impact.TTF "$winefontsdir"
    register_font Impact.TTF "Impact (TrueType)"

    try cabextract -q --directory="$WINETRICKS_TMP" "$WINETRICKS_CACHE"/times32.exe
    try cp -f "$WINETRICKS_TMP"/Times*.TTF "$winefontsdir"
    register_font Times.TTF "Times New Roman (TrueType)"
    register_font Timesbd.TTF "Times New Roman Bold (TrueType)"
    register_font Timesbi.TTF "Times New Roman Bold Italic (TrueType)"
    register_font Timesi.TTF "Times New Roman Italic (TrueType)"

    try cabextract -q --directory="$WINETRICKS_TMP" "$WINETRICKS_CACHE"/trebuc32.exe
    try cp -f "$WINETRICKS_TMP"/trebuc*.ttf "$winefontsdir"
    register_font Trebuc.TTF "Trebucet MS (TrueType)"
    register_font Trebucbd.TTF "Trebucet MS Bold (TrueType)"
    register_font Trebucbi.TTF "Trebucet MS Bold Italic (TrueType)"
    register_font Trebucit.TTF "Trebucet MS Italic (TrueType)"

    try cabextract -q --directory="$WINETRICKS_TMP" "$WINETRICKS_CACHE"/verdan32.exe
    try cp -f "$WINETRICKS_TMP"/Verdana*.TTF "$winefontsdir"
    register_font Verdana.TTF "Verdana (TrueType)"
    register_font Verdanab.TTF "Verdana Bold (TrueType)"
    register_font Verdanaz.TTF "Verdana Bold Italic (TrueType)"
    register_font Verdanai.TTF "Verdana Italic (TrueType)"

    try cabextract -q --directory="$WINETRICKS_TMP" "$WINETRICKS_CACHE"/webdin32.exe
    try cp -f "$WINETRICKS_TMP"/Webdings.TTF "$winefontsdir"
    register_font Webdings.TTF "Webdings (TrueType)"
}

#----------------------------------------------------------------

load_cygwin() {
    download cygwin http://cygwin.com/setup.exe 5cfb8ebe4f385b0fcffa04d22d607ec75ea05180
    mkdir -p "$DRIVE_C"/cygpkgs
    cp "$WINETRICKS_CACHE/cygwin/setup.exe" "$DRIVE_C"/cygpkgs
    cd "$DRIVE_C"/cygpkgs
    try $WINE setup.exe
    cd "$olddir"
}

#----------------------------------------------------------------

load_d3dx9() {
    helper_directx_dl

    # Kinder, less invasive directx - only extract and override d3dx9_??.dll
    cabextract -d "$WINETRICKS_TMP" -L -F '*d3dx9*x86*' "$WINETRICKS_CACHE"/$DIRECTX_NAME
    for x in `ls "$WINETRICKS_TMP"/*.cab`
    do
      cabextract -d "$WINDIR"/system32 -L -F '*.dll' "$x"
    done

    # For now, not needed, but when Wine starts preferring our builtin dll over native it will be.
    override_dlls native d3dx9_24 d3dx9_25 d3dx9_26 d3dx9_27 d3dx9_28 d3dx9_29 d3dx9_30
    override_dlls native d3dx9_31 d3dx9_32 d3dx9_33 d3dx9_34 d3dx9_35 d3dx9_36 d3dx9_37
    override_dlls native d3dx9_38 d3dx9_39 d3dx9_40 d3dx9_41 d3dx9_42
}

#----------------------------------------------------------------
load_dinput8() {
    helper_directx_dl

    cabextract -d "$WINETRICKS_TMP" -L -F 'dxnt.cab' "$WINETRICKS_CACHE"/$DIRECTX_NAME
    cabextract -d "$WINDIR"/system32 -L -F 'dinput8.dll' "$WINETRICKS_TMP/dxnt.cab"

    try $WINE regsvr32 dinput8

    override_dlls native dinput8
}

#----------------------------------------------------------------

load_dirac08() {
    download . http://codecpack.nl/dirac_dsfilter_080.exe aacfcddf6b2636de5f0a50422ba9155e395318af
    try $WINE "$WINETRICKS_CACHE"/dirac_dsfilter_080.exe $WINETRICKS_SILENT
}

#----------------------------------------------------------------

load_directplay() {
    helper_directx_dl

    cabextract -d "$WINETRICKS_TMP" -L -F dxnt.cab "$WINETRICKS_CACHE"/$DIRECTX_NAME
    cabextract -d "$WINDIR"/system32 -L -F 'dplaysvr.exe' "$WINETRICKS_TMP/dxnt.cab"
    cabextract -d "$WINDIR"/system32 -L -F 'dplayx.dll' "$WINETRICKS_TMP/dxnt.cab"
    cabextract -d "$WINDIR"/system32 -L -F 'dpnet.dll' "$WINETRICKS_TMP/dxnt.cab"
    cabextract -d "$WINDIR"/system32 -L -F 'dpnhpast.dll' "$WINETRICKS_TMP/dxnt.cab"
    cabextract -d "$WINDIR"/system32 -L -F 'dpwsockx.dll' "$WINETRICKS_TMP/dxnt.cab"

    try $WINE regsvr32 dplayx.dll
    try $WINE regsvr32 dpnet.dll
    try $WINE regsvr32 dpnhpast.dll

    override_dlls native dplayx dpnet dpnhpast dpwsockx
}

#----------------------------------------------------------------

load_directx9() {
    helper_directx_dl

    # Stefan suggested that, when installing, one should override as follows:
    # 1) use builtin wintrust (we don't run native properly somehow?)
    # 2) disable mscoree (else if it's present some module misbehaves?)
    # 3) override native any directx DLL whose Wine version doesn't register itself well yet
    # For #3, I have no idea which DLLs don't register themselves well yet,
    # so I'm just listing a few of the basic ones.  Let's whittle that
    # list down as soon as we can.
    echo "You probably shouldn't be using this. It's VERY invasive."
    echo "Use 'winetricks d3dx9' instead."
    set_winver win2k
    WINEDLLOVERRIDES="wintrust=b,mscoree=,ddraw,d3d8,d3d9,dsound,dinput=n" \
       try $WINE "$WINETRICKS_CACHE"/$DIRECTX_NAME /t:"$WINETRICKS_TMP_WIN" $WINETRICKS_QUIET

    # How many of these do we really need?
    # We should probably remove most of these...?
    override_dlls native d3dim d3drm d3dx8 d3dx9_24 d3dx9_25 d3dx9_26 d3dx9_27 d3dx9_28 d3dx9_29
    override_dlls native d3dx9_30 d3dx9_31 d3dx9_32 d3dx9_33 d3dx9_34 d3dx9_35 d3dx9_36 d3dx9_37
    override_dlls native d3dx9_38 d3dx9_39 d3dx9_40 d3dx9_41 d3dx9_42 d3dxof
    override_dlls native dciman32 ddrawex devenum dmband dmcompos dmime dmloader dmscript dmstyle
    override_dlls native dmsynth dmusic dmusic32 dnsapi dplay dplayx dpnaddr dpnet dpnhpast dpnlobby
    override_dlls native dswave dxdiagn mscoree msdmo qcap quartz streamci
    override_dlls builtin d3d8 d3d9 dinput dinput8 dsound

    # Should be below, but fails on Wine when used silently.
    #if [ $WINETRICKS_QUIET ]
    #then
    #    try $WINE "$WINETRICKS_TMP_WIN"/DXSETUP.exe /silent
    #else
    #    try $WINE "$WINETRICKS_TMP_WIN"/DXSETUP.exe
    #fi

    try $WINE "$WINETRICKS_TMP_WIN"/DXSETUP.exe

    unset_winver
}

#----------------------------------------------------------------

load_divx() {
    # 6.8.2: 02203fdc4dddd13e789c39b22902837da31d2a1d ?
    # 6.8.2: e36bf87c1675d0cf9169839bc0cd8f866b9db026 as of 4 Jun 2008 as http://download.divx.com/divx/DivXInstaller.exe
    # 6.8.3: f4f4387ef89316aea440a29f3e24c1f1945e14af as of 20 Jun 2008 as http://download.divx.com/divx/abt/b1/DivXInstaller.exe
    # 6.8.4: c5fcb1465a1bb24d1c104c2588fdb6706d1e1476 as of 10 Jul 2008 as http://download.divx.com/divx/abt/b1/DivXInstaller.exe
    # 6.8.4: d28a2b041f4af45d22c4dedfe7608f2958cf997d as of 23 Aug 2008 as http://download.divx.com/divx/DivXInstaller.exe

    # 7.? 4d91ef90ae26a6088851560c4263ef0cdbf09123 as of 22 Mar 2009 as http://download.divx.com/divx/DivXInstaller.exe
    # 7.0.? 19c9ba3104025d1fab335e405e7f411dfbbcb477 as of 28 May 2009 as http://download.divx.com/divx/DivXInstaller.exe
    # 7.0.? 786aef0f421df5e7358d2d740d9911f9afd055de as of 24 June 2009 as http://download.divx.com/divx/DivXInstaller.exe
    # 7.0.? ad420bf8bf72e924e658c9c6ad6bba76b848fb79 as of 23 Sep 2009 as http://download.divx.com/divx/DivXInstaller.exe
    # 7.0.? 3385aa8f6ba64ae32e06f651bbbea247bcc1a44d as of 12 Dec 2009 as http://download.divx.com/divx/DivXInstaller.exe
    
    download divx-7 http://download.divx.com/divx/DivXInstaller.exe 3385aa8f6ba64ae32e06f651bbbea247bcc1a44d

    try $WINE "$WINETRICKS_CACHE"/divx-7/DivXInstaller
}

#----------------------------------------------------------------

load_dcom98() {
    # Install native dcom per http://wiki.winehq.org/NativeDcom
    # to avoid http://bugs.winehq.org/show_bug.cgi?id=4228
    # See http://www.microsoft.com/downloads/details.aspx?familyid=08b1ac1b-7a11-43e8-b59d-0867f9bdda66
    download . http://download.microsoft.com/download/d/1/3/d13cd456-f0cf-4fb2-a17f-20afc79f8a51/DCOM98.EXE aff002bd03f17340b2bef2e6b9ea8e3798e9ccc1

    # Pick win98 so we can install native dcom
    set_winver win98

    # Avoid "err:setupapi:SetupDefaultQueueCallbackA copy error 5 ..."
    # Those messages are suspect, probably shouldn't be err's.
    rm -f "$WINDIR"/system32/ole32.dll
    rm -f "$WINDIR"/system32/olepro32.dll
    rm -f "$WINDIR"/system32/oleaut32.dll
    rm -f "$WINDIR"/system32/rpcrt4.dll

    # Normally only need to override ole32, but overriding advpack
    # as well gets us the correct exit status.
    WINEDLLOVERRIDES="ole32,advpack=n" try $WINE "$WINETRICKS_CACHE"/DCOM98.EXE $WINETRICKS_QUIET

    # Set native DCOM by default for all apps (ok, this might be overkill)
    override_dlls native,builtin ole32 oleaut32 rpcrt4

    # but not for a few builtin apps that don't like it
    override_app_dlls explorer.exe builtin ole32 oleaut32 rpcrt4
    override_app_dlls iexplore.exe builtin ole32 oleaut32 rpcrt4
    override_app_dlls services.exe builtin ole32 oleaut32 rpcrt4
    override_app_dlls wineboot.exe builtin ole32 oleaut32 rpcrt4
    override_app_dlls winedevice.exe builtin ole32 oleaut32 rpcrt4

    # and undo version win98
    unset_winver
}

#----------------------------------------------------------------

load_dotnet11() {
    DOTNET_INSTALL_DIR="$WINDIR/Microsoft.NET/Framework/v1.1.4322"

    # If this is just a dependency check, don't re-install
    if test $PACKAGE != dotnet11 && test -d "$DOTNET_INSTALL_DIR"
    then
        echo "prerequisite dotnet11 already installed, skipping"
        return
    fi

    # need corefonts, else installer crashes
    load_corefonts

    # http://www.microsoft.com/downloads/details.aspx?FamilyId=262D25E3-F589-4842-8157-034D1E7CF3A3
    download dotnet11 http://download.microsoft.com/download/a/a/c/aac39226-8825-44ce-90e3-bf8203e74006/dotnetfx.exe 16a354a2207c4c8846b617cbc78f7b7c1856340e
    if [ $WINETRICKS_QUIET ]
    then
        try $WINE "$WINETRICKS_CACHE"/dotnet11/dotnetfx.exe /q /C:"install /q"
    else
        try $WINE "$WINETRICKS_CACHE"/dotnet11/dotnetfx.exe
    fi
}

#----------------------------------------------------------------

load_dotnet11sdk() {
    load_dotnet11

    warn "Installer hangs at end... not sure if it works fully."
    # http://www.microsoft.com/downloads/details.aspx?familyid=9B3A2CA6-3647-4070-9F41-A333C6B9181D
    download dotnet11sdk http://download.microsoft.com/download/5/2/0/5202f918-306e-426d-9637-d7ee26fbe507/setup.exe 9509b14924bcaf84a7780de3f6ad7894004c3450
    cd "$WINETRICKS_CACHE"/dotnet11sdk
    try $WINE setup.exe
    cd "$olddir"
}

#----------------------------------------------------------------

load_dotnet20() {
    # If this is just a dependency check, don't re-install
    if test $PACKAGE != dotnet20 && test -f "$WINDIR"/system32/l_intl.nls
    then
        echo "prerequisite dotnet20 already installed, skipping"
        return
    fi
    warn "Instaling .net 2.0 runtime.  Can take several minutes.  See http://wiki.winehq.org/MicrosoftDotNet for tips."

    # Recipe from http://bugs.winehq.org/show_bug.cgi?id=10467#c57
    test -d "$WINDIR/gecko" || test -d "$WINDIR/system32/gecko" || load_gecko
    set_winver win2k
    # See http://kegel.com/wine/l_intl-sh.txt for how l_intl.nls was generated
    download dotnet20 http://kegel.com/wine/l_intl.nls 0d2e3f025bcdf852b192c9408a361ac2659fa249
    try cp -f "$WINETRICKS_CACHE"/dotnet20/l_intl.nls "$WINDIR/system32/"

    # http://www.microsoft.com/downloads/details.aspx?FamilyID=0856eacb-4362-4b0d-8edd-aab15c5e04f5
    download dotnet20 http://download.microsoft.com/download/5/6/7/567758a3-759e-473e-bf8f-52154438565a/dotnetfx.exe a3625c59d7a2995fb60877b5f5324892a1693b2a
    if [ "$WINETRICKS_QUIET"x = ""x ]
    then
       try $WINE "$WINETRICKS_CACHE"/dotnet20/dotnetfx.exe
    else
       try $WINE "$WINETRICKS_CACHE"/dotnet20/dotnetfx.exe /q /c:"install.exe /q"
    fi
    unset_winver
}
#----------------------------------------------------------------

load_dotnet20sp2() {
    warn "Instaling .net 2.0 runtime.  Can take several minutes.  See http://wiki.winehq.org/MicrosoftDotNet for tips."

    # Recipe from http://bugs.winehq.org/show_bug.cgi?id=10467#c57
    test -d "$WINDIR/gecko" || test -d "$WINDIR/system32/gecko" || load_gecko
    #set_winver win2k
    # See http://kegel.com/wine/l_intl-sh.txt for how l_intl.nls was generated
    download dotnet20 http://kegel.com/wine/l_intl.nls 0d2e3f025bcdf852b192c9408a361ac2659fa249
    try cp -f "$WINETRICKS_CACHE"/dotnet20/l_intl.nls "$WINDIR/system32/"

    # http://www.microsoft.com/downloads/details.aspx?familyid=5B2C0358-915B-4EB5-9B1D-10E506DA9D0F
    download dotnet20 http://download.microsoft.com/download/c/6/e/c6e88215-0178-4c6c-b5f3-158ff77b1f38/NetFx20SP2_x86.exe 22d776d4d204863105a5db99e8b8888be23c61a7
    if [ "$WINETRICKS_QUIET"x = ""x ]
    then
       try $WINE "$WINETRICKS_CACHE"/dotnet20/NetFx20SP2_x86.exe
    else
       try $WINE "$WINETRICKS_CACHE"/dotnet20/NetFx20SP2_x86.exe /q /c:"install.exe /q"
    fi
    unset_winver
}

#----------------------------------------------------------------

load_dotnet20sdk() {
    load_dotnet20

    # http://www.microsoft.com/downloads/details.aspx?familyid=9B3A2CA6-3647-4070-9F41-A333C6B9181D
    download dotnet20sdk http://download.microsoft.com/download/c/4/b/c4b15d7d-6f37-4d5a-b9c6-8f07e7d46635/setup.exe 4e4b1072b5e65e855358e2028403f2dc52a62ab4
    cd "$WINETRICKS_CACHE"/dotnet20sdk
    try $WINE setup.exe
    cd "$olddir"
}

#----------------------------------------------------------------

load_dotnet30() {
    # If this is just a dependency check, don't re-install
    if test $PACKAGE != dotnet30 && test -d "$WINDIR/Microsoft.NET/Framework/v3.0/Microsoft .NET Framework 3.0"
    then
        echo "prerequisite dotnet30 already installed, skipping"
        return
    fi

    warn "Instaling .net 3.0 runtime.  Can take 15-30 minutes.  See http://wiki.winehq.org/MicrosoftDotNet for tips."
    load_dotnet20

    # http://msdn.microsoft.com/en-us/netframework/bb264589.aspx
    download dotnet30 http://download.microsoft.com/download/3/F/0/3F0A922C-F239-4B9B-9CB0-DF53621C57D9/dotnetfx3.exe f3d2c3c7e4c0c35450cf6dab1f9f2e9e7ff50039

    export _SFX_CAB_SHUTDOWN_REQUEST=1
    if [ "$WINETRICKS_QUIET"x = ""x ]
    then
       try $WINE "$WINETRICKS_CACHE"/dotnet30/dotnetfx3.exe
    else
       try $WINE "$WINETRICKS_CACHE"/dotnet30/dotnetfx3.exe /q /c:"install.exe /q"
    fi
}

#----------------------------------------------------------------

load_dotnet35() {
    warn "This does not work yet"

    # According to AF's recipe, installing dotnet30 first works around msi bugs
    load_dotnet30

    # http://www.microsoft.com/downloads/details.aspx?FamilyId=333325FD-AE52-4E35-B531-508D977D32A6
    download dotnet35 http://download.microsoft.com/download/6/0/f/60fc5854-3cb8-4892-b6db-bd4f42510f28/dotnetfx35.exe 

    if [ "$WINETRICKS_QUIET"x = ""x ]
    then
       try $WINE "$WINETRICKS_CACHE"/dotnet35/dotnetfx35.exe
    else
       try $WINE "$WINETRICKS_CACHE"/dotnet35/dotnetfx35.exe /q /c:"install.exe /q"
    fi
}

#----------------------------------------------------------------

do_droid() {
    download . ${DROID_URL}$1';hb=HEAD'   $3  $1
    try cp -f "$WINETRICKS_CACHE"/$1 "$winefontsdir"
    register_font $1 "$2"
}

load_droid() {
    # See http://en.wikipedia.org/wiki/Droid_(font)
    DROID_URL='http://android.git.kernel.org/?p=platform/frameworks/base.git;a=blob_plain;f=data/fonts/'

    do_droid DroidSans-Bold.ttf        "Droid Sans Bold"         ada4e79c592f3c54546b7587b48f2b232d95ce2f
    do_droid DroidSansFallback.ttf     "Droid Sans Fallback"     2f8a266389a8e22f68f402b775731eec6b760334
    do_droid DroidSansJapanese.ttf     "Droid Sans Japanese"     b3a248c11692aa88a30eb25df425b8910fe05dc5
    do_droid DroidSansMono.ttf         "Droid Sans Mono"         f0815c6f36c72be1d0f2f5e2b82fa85c8bf95655
    do_droid DroidSans.ttf             "Droid Sans"              da5b3c7758a2c8fbc4775beb69d7150493c7d312
    do_droid DroidSerif-BoldItalic.ttf "Droid Serif Bold Italic" c1602dc11bf0f7131aec21c7c3888195ad78e486
    do_droid DroidSerif-Bold.ttf       "Droid Serif Bold"        d7896b9c0723299553e95a00d27cbe52f7515c8c
    do_droid DroidSerif-Italic.ttf     "Droid Serif Italic"      117941be102c8f38a86a70ebccaecb8078f7027e
    do_droid DroidSerif-Regular.ttf    "Droid Serif"             7f243858e496ed1bb1faca9f3a7bbe52defcbb5d
}

#----------------------------------------------------------------

# Fake IE per workaround in http://bugs.winehq.org/show_bug.cgi?id=3453
# Just the first registry key works for most apps.
# The App Paths part is required by a few apps, like Quickbooks Pro;
# see http://windowsxp.mvps.org/ie/qbooks.htm
set_fakeie6() {

    cat > "$WINETRICKS_TMP"/fakeie6.reg <<"_EOF_"
REGEDIT4

[HKEY_LOCAL_MACHINE\Software\Microsoft\Internet Explorer]
"Version"="6.0.2900.2180"

[HKEY_LOCAL_MACHINE\Software\Microsoft\Windows\CurrentVersion\App Paths\IEXPLORE.EXE]
_EOF_

    echo -n '@="' >>"$WINETRICKS_TMP"/fakeie6.reg
    echo -n "${programfilesdir_win}" | sed "s/\\\\/\\\\\\\\/" >>"$WINETRICKS_TMP"/fakeie6.reg
    echo '\\\\Internet Explorer\\\\iexplore.exe"' >>"$WINETRICKS_TMP"/fakeie6.reg

    echo -n '"PATH"="' >>"$WINETRICKS_TMP"/fakeie6.reg
    echo -n "${programfilesdir_win}" | sed "s/\\\\/\\\\\\\\/" >>"$WINETRICKS_TMP"/fakeie6.reg
    echo '\\\\Internet Explorer"' >>"$WINETRICKS_TMP"/fakeie6.reg

    try_regedit "$WINETRICKS_TMP"/fakeie6.reg

    # On old wineprefixes iexplore.exe is not created. Create a fake dll using
    # shdocvw.dll that should have similar VERSIONINFO.
    if [ ! -f "$programfilesdir_unix/Internet Explorer/iexplore.exe" ]
    then
        echo "You have an old wineprefix without iexplore.exe. Will create a fake now"
        if [ ! -d "$programfilesdir_unix/Internet Explorer/iexplore.exe" ]
        then
            try mkdir "$programfilesdir_unix/Internet Explorer";
        fi
        try cp -f "$WINDIR/system32/shdocvw.dll" "$programfilesdir_unix/Internet Explorer/iexplore.exe"
    fi
}

#----------------------------------------------------------------

load_firefox() {
    download . "http://releases.mozilla.org//pub/mozilla.org/firefox/releases/3.5.5/win32/en-US/Firefox%20Setup%203.5.5.exe" 39126be271b28ef50c48e3acbe52ee83bbf31066 "Firefox Setup 3.5.5.exe"
    if [ "$WINETRICKS_QUIET"x = ""x ]
    then
       try $WINE "$WINETRICKS_CACHE"/"Firefox Setup 3.5.5.exe"
    else
       try $WINE "$WINETRICKS_CACHE"/"Firefox Setup 3.5.5.exe" -ms
    fi
}

#----------------------------------------------------------------

load_ffdshow() {
    # ffdshow
    download . $SOURCEFORGE/ffdshow-tryout/ffdshow_beta5_rev2033_20080705_clsid.exe 6da6837e2f400923ff5294a6591a88a3eee5ee40
    try $WINE "$WINETRICKS_CACHE"/ffdshow_beta5_rev2033_20080705_clsid.exe $WINETRICKS_SILENT
}

#----------------------------------------------------------------

load_flash() {
    # www.adobe.com/products/flashplayer/

    # Active X plugin
    # http://blogs.adobe.com/psirt/2008/03/preparing_for_april_flash_play.html
    # http://fpdownload.macromedia.com/get/flashplayer/current/licensing/win/install_flash_player_active_x.msi
    # 2008-04-01: old version sha1sum f4dd1c0c715b791db2c972aeba90d3b78372996a
    # 2008-04-18: new version sha1sum 04ac79c4f1eb1e1ca689f27fa71f12bb5cd11cc2
    # Version 10 http://fpdownload.macromedia.com/get/flashplayer/current/install_flash_player_ax.exe
    # 2008-11-27: 10 sha1sum 7f6850ae815e953311bb94a8aa9d226f97a646dd
    # 2009-02-27: sha1sum 86745020a25edc9695a1a6a4d59eae375665a0b3
    # 2009-07-31: sha1sum 11a81ad1b19344c28b1e1249169f15dfbd2a04f5
    # 2009-12-09: sha1sum f4ec0e95099e354fd01cd3bb27c202f54932dc70

    download . http://fpdownload.macromedia.com/get/flashplayer/current/install_flash_player_ax.exe f4ec0e95099e354fd01cd3bb27c202f54932dc70
    try $WINE "$WINETRICKS_CACHE"/install_flash_player_ax.exe $WINETRICKS_S

    # Mozilla / Firefox plugin
    # 2008-07-22: sha1sum 1e6f7627784a5b791e99ae9ad63133dc11c7940b
    # 2008-11-27: sha1sum 20ec0300a8cae19105c903a7ec6c0801e016beb0
    # 2009-02-27: sha1sum 770db9ad471ffd4357358bc16ff0bb6c98d71e5d
    # 2009-07-31: sha1sum 9590fb87cc33d3a3a1f2f42a1918f06b9f0fd88d
    # 2009-12-09: sha1sum ccb4811b1cc26721c4abb2e5a080868acdee7b87
    
    download . http://fpdownload.macromedia.com/get/flashplayer/current/install_flash_player.exe ccb4811b1cc26721c4abb2e5a080868acdee7b87
    try $WINE "$WINETRICKS_CACHE"/install_flash_player.exe $WINETRICKS_S
}

#----------------------------------------------------------------

load_fontfix() {
    # some versions of ukai.ttf and uming.ttf crash .net and picasa
    # See http://bugs.winehq.org/show_bug.cgi?id=7098#c9
    # Could fix globally, but that needs root, so just fix for wine
    if test -f /usr/share/fonts/truetype/arphic/ukai.ttf
    then
        gotsum=`$SHA1SUM < /usr/share/fonts/truetype/arphic/ukai.ttf | sed 's/ .*//'`
        # FIXME: do all affected versions of the font have same sha1sum as Gutsy?  Seems unlikely.
        if [ "$gotsum"x = "96e1121f89953e5169d3e2e7811569148f573985"x ]
        then
            download . https://launchpadlibrarian.net/1499628/ttf-arphic-ukai_0.1.20060108.orig.tar.gz 92e577602d71454a108968e79ab667451f3602a2
            cd "$WINETRICKS_TMP/"
            gunzip -dc "$WINETRICKS_CACHE/ttf-arphic-ukai_0.1.20060108.orig.tar.gz" | tar -xf -
            try mv ttf-arphic-ukai-0.1.20060108/*.ttf "$winefontsdir"
            cd "$olddir"
        fi
    fi

    if test -f /usr/share/fonts/truetype/arphic/uming.ttf
    then
        gotsum=`$SHA1SUM < /usr/share/fonts/truetype/arphic/uming.ttf | sed 's/ .*//'`
        if [ "$gotsum"x = "2a4f4a69e343c21c24d044b2cb19fd4f0decc82c"x ]
        then
            download . https://launchpadlibrarian.net/1564410/ttf-arphic-uming_0.1.20060108.orig.tar.gz 1439cdd731906e9e5311f320c2cb33262b24ef91
            cd "$WINETRICKS_TMP/"
            gunzip -dc "$WINETRICKS_CACHE/ttf-arphic-uming_0.1.20060108.orig.tar.gz" | tar -xf -
            try mv ttf-arphic-uming-0.1.20060108/*.ttf "$winefontsdir"
            cd "$olddir"
        fi
    fi

    # Focht says Samyak is bad news, and font substitution isn't a good workaround.
    if xlsfonts | grep -i samyak
    then
        warn "Please uninstall the Samyak font.  It has been known to cause problems."
    fi
}

#----------------------------------------------------------------
load_fs_disable() {
    cat > "$WINETRICKS_TMP"/fs_disable.reg <<_EOF_
REGEDIT4

[HKEY_CURRENT_USER\Control Panel\Desktop]
"FontSmoothing"="0"
"FontSmoothingGamma"=dword:00000578
"FontSmoothingOrientation"=dword:00000001
"FontSmoothingType"=dword:00000000

_EOF_
    try_regedit "$WINETRICKS_TMP"/fs_disable.reg
}
#----------------------------------------------------------------
load_fs_grayscale() {
    cat > "$WINETRICKS_TMP"/fs_grayscale.reg <<_EOF_

REGEDIT4

[HKEY_CURRENT_USER\Control Panel\Desktop]
"FontSmoothing"="2"
"FontSmoothingGamma"=dword:00000578
"FontSmoothingOrientation"=dword:00000001
"FontSmoothingType"=dword:00000001

_EOF_
    try_regedit "$WINETRICKS_TMP"/fs_grayscale.reg
}
#----------------------------------------------------------------
load_fs_bgr() {
    cat > "$WINETRICKS_TMP"/fs_bgr.reg <<_EOF_

REGEDIT4

[HKEY_CURRENT_USER\Control Panel\Desktop]
"FontSmoothing"="2"
"FontSmoothingGamma"=dword:00000578
"FontSmoothingOrientation"=dword:00000000
"FontSmoothingType"=dword:00000002

_EOF_
    try_regedit "$WINETRICKS_TMP"/fs_bgr.reg
}
#----------------------------------------------------------------
load_fs_rgb() {
    cat > "$WINETRICKS_TMP"/fs_rgb.reg <<_EOF_

REGEDIT4

[HKEY_CURRENT_USER\Control Panel\Desktop]
"FontSmoothing"="2"
"FontSmoothingGamma"=dword:00000578
"FontSmoothingOrientation"=dword:00000001
"FontSmoothingType"=dword:00000002

_EOF_
    try_regedit "$WINETRICKS_TMP"/fs_rgb.reg
}
#----------------------------------------------------------------

load_gecko() {
    # Load the HTML rendering Engine (Gecko)
    # FIXME: shouldn't this code be in some script installed
    # as part of Wine instead of in winetricks?
    # (e.g. we hardcode gecko's url here, but it's normally
    # only hardcoded in wine.inf, and fetched from the registry thereafter,
    # so we're adding a maintenance burden here.)
    case `$WINE --version` in
    wine-0*|wine-1.0*|wine-1.1|wine-1.1.?|wine-1.1.11)
        GECKO_DIR="$WINDIR"
        GECKO_VERSION=0.1.0
        GECKO_SHA1SUM=c16f1072dc6b0ced20935662138dcf019a38cd56
        ;;
    wine-1.1.1[234]*)
        GECKO_DIR="$WINDIR"
        GECKO_VERSION=0.9.0
        GECKO_SHA1SUM=5cf410ff7fdd3f9d625f481f9d409968728d3d09
        ;;
    wine-1.1.1[56789]*|wine-1.1.2[0123456]*)
        GECKO_DIR="$WINDIR"
        GECKO_VERSION=0.9.1
        GECKO_SHA1SUM=9a49fc691740596517e381b47096a4bdf19a87d8
        ;;
    *)
        GECKO_DIR="$WINDIR/system32"
        GECKO_VERSION=1.0.0
        GECKO_ARCH=-x86
        GECKO_SHA1SUM=afa22c52bca4ca77dcb9edb3c9936eb23793de01
        ;;
    esac

    if test ! -f "$WINETRICKS_CACHE"/wine_gecko-$GECKO_VERSION$GECKO_ARCH.cab
    then
       # FIXME: busted if using curl!
       download . "http://downloads.sourceforge.net/wine/wine_gecko-$GECKO_VERSION$GECKO_ARCH.cab" \
                $GECKO_SHA1SUM wine_gecko-$GECKO_VERSION$GECKO_ARCH.cab
    fi

    cat > "$WINETRICKS_TMP"/geckopath.reg <<_EOF_
REGEDIT4

[HKEY_CURRENT_USER\\Software\\Wine\\MSHTML\\$GECKO_VERSION]
_EOF_

    printf '"GeckoPath"="' >>"$WINETRICKS_TMP"/geckopath.reg
    case $GECKO_VERSION in
    0.*)
        printf 'c:\\windows\\gecko\\'$GECKO_VERSION'\\wine_gecko\\"' |
           sed "s/\\\\/\\\\\\\\/g" >> "$WINETRICKS_TMP"/geckopath.reg
        ;;
    1.*)
        printf 'c:\\windows\\system32\\gecko\\'$GECKO_VERSION'\\wine_gecko\\"' |
           sed "s/\\\\/\\\\\\\\/g" >> "$WINETRICKS_TMP"/geckopath.reg
        ;;
    esac

    # extract the files
    mkdir -p "$GECKO_DIR/gecko/$GECKO_VERSION"
    cd "$GECKO_DIR/gecko/$GECKO_VERSION"
    try cabextract $WINETRICKS_UNIXQUIET "$WINETRICKS_CACHE"/wine_gecko-$GECKO_VERSION$GECKO_ARCH.cab
    cd "$olddir"

    # set install-path
    try_regedit "$WINETRICKS_TMP"/geckopath.reg
    
    # register the dll, since it was disabled before
    try $WINE regsvr32 mshtml
}

#----------------------------------------------------------------

load_gecko_dbg() {
    # Load the HTML rendering Engine (Gecko), with debugging symbols
    # FIXME: shouldn't this code be in some script installed
    # as part of Wine instead of in winetricks?
    # (e.g. we hardcode gecko's url here, but it's normally
    # only hardcoded in wine.inf, and fetched from the registry thereafter,
    # so we're adding a maintenance burden here.)
    case `$WINE --version` in
    wine-0*|wine-1.0*|wine-1.1|wine-1.1.?|wine-1.1.11)
        die "There isn't a gecko debug build for your Wine version."
        ;;
    wine-1.1.1[234]*)
        GECKO_DIR="$WINDIR"
        GECKO_VERSION=0.9.0
        GECKO_SHA1SUM=23e354a82d7b7e61a6abe0384cc44669fbf92f86
        ;;
    wine-1.1.1[56789]*|wine-1.1.2[0123456]*)
        GECKO_DIR="$WINDIR"
        GECKO_VERSION=0.9.1
        GECKO_SHA1SUM=a9b58d3330f8c78524fe4683f348302bfce96ff4
        ;;
    *)
        GECKO_DIR="$WINDIR/system32"
        GECKO_VERSION=1.0.0
        GECKO_ARCH=-x86
        GECKO_SHA1SUM=2de16b443826295f646cd5d54313ca421fd71210
        ;;
    esac

    if test ! -f "$WINETRICKS_CACHE"/wine_gecko-$GECKO_VERSION$GECKO_ARCH-dbg.cab
    then
       # FIXME: busted if using curl!
       download . "http://downloads.sourceforge.net/wine/wine_gecko-$GECKO_VERSION$GECKO_ARCH-dbg.cab" $GECKO_SHA1SUM wine_gecko-$GECKO_VERSION$GECKO_ARCH-dbg.cab
    fi

    cat > "$WINETRICKS_TMP"/geckopath.reg <<_EOF_
REGEDIT4

[HKEY_CURRENT_USER\\Software\\Wine\\MSHTML\\$GECKO_VERSION]
_EOF_

    printf '"GeckoPath"="' >>"$WINETRICKS_TMP"/geckopath.reg
    if [ ! "$GECKO_VERSION" = "1.0.0" ]
    then
        printf 'c:\\windows\\gecko\\'$GECKO_VERSION'\\wine_gecko\\"' | sed "s/\\\\/\\\\\\\\/g" >>"$WINETRICKS_TMP"/geckopath.reg
    else
        printf 'c:\\windows\\system32\\gecko\\'$GECKO_VERSION'\\wine_gecko\\"' | sed "s/\\\\/\\\\\\\\/g" >>"$WINETRICKS_TMP"/geckopath.reg
    fi

    # extract the files
    mkdir -p "$GECKO_DIR/gecko/$GECKO_VERSION"
    cd "$GECKO_DIR/gecko/$GECKO_VERSION"
    try cabextract $WINETRICKS_UNIXQUIET "$WINETRICKS_CACHE"/wine_gecko-$GECKO_VERSION$GECKO_ARCH-dbg.cab
    cd "$olddir"

    # set install-path
    try_regedit "$WINETRICKS_TMP"/geckopath.reg
    
    # register the dll, since it was disabled before
    try $WINE regsvr32 mshtml
}

#----------------------------------------------------------------

load_gdiplus() {
    # http://www.microsoft.com/downloads/details.aspx?familyid=6A63AB9C-DF12-4D41-933C-BE590FEAA05A&displaylang=en
    download . http://download.microsoft.com/download/a/b/c/abc45517-97a0-4cee-a362-1957be2f24e1/WindowsXP-KB975337-x86-ENU.exe b9a84bc3de92863bba1f5eb1d598446567fbc646
    try $WINE "$WINETRICKS_CACHE"/WindowsXP-KB975337-x86-ENU.exe /extract:$WINETRICKS_TMP_WIN $WINETRICKS_QUIET
    # And then make it globally available.
    try cp "$WINETRICKS_TMP/asms/10/msft/windows/gdiplus/gdiplus.dll" "$WINDIR"/system32/

    # For some reason, native,builtin isn't good enough...?
    override_dlls native gdiplus
}

#----------------------------------------------------------------

load_glsl_disable() {
    echo "Disabling GLSL"
    cat > "$WINETRICKS_TMP"/disableglsl.reg <<_EOF_
REGEDIT4

[HKEY_CURRENT_USER\Software\Wine\Direct3D]
"UseGLSL"="disabled"

_EOF_
    try_regedit "$WINETRICKS_TMP"/disableglsl.reg
}

#----------------------------------------------------------------

load_glsl_enable() {
    echo "Enabling GLSL"
    cat > "$WINETRICKS_TMP"/enableglsl.reg <<_EOF_
REGEDIT4

[HKEY_CURRENT_USER\Software\Wine\Direct3D]
"UseGLSL"="enabled"

_EOF_
    try_regedit "$WINETRICKS_TMP"/enableglsl.reg
}

#----------------------------------------------------------------

load_hosts() {
    # Create fake system32\drivers\etc\hosts and system32\drivers\etc\services files.
    # The hosts file is used to map network names to IP addresses without DNS.
    # The services file is used map service names to network ports.
    # Some apps depend on these files, but they're not implemented in wine.
    # Fortunately, empty files in the correct location satisfy those apps.
    # See http://bugs.winehq.org/show_bug.cgi?id=12076
    mkdir -p "$WINDIR"/system32/drivers/etc
    touch "$WINDIR"/system32/drivers/etc/hosts
    touch "$WINDIR"/system32/drivers/etc/services
}

#----------------------------------------------------------------

set_heapcheck() {
    cat > "$WINETRICKS_TMP"/heapcheck.reg <<_EOF_
REGEDIT4

[HKEY_LOCAL_MACHINE\System\CurrentControlSet\Control\Session Manager]
"GlobalFlag"=dword:00200030

_EOF_
    try_regedit "$WINETRICKS_TMP"/heapcheck.reg
}

#----------------------------------------------------------------

load_icodecs() {
    # http://downloadcenter.intel.com/Detail_Desc.aspx?strState=LIVE&ProductID=355&DwnldID=2846
    download . http://downloadmirror.intel.com/2846/eng/codinstl.exe 2c5d64f472abe3f601ce352dcca75b4f02996f8a
    try $WINE "$WINETRICKS_CACHE"/codinstl.exe
    # Work around bug in codec's installer?
    # http://support.britannica.com/other/touchthesky/win/issues/TSTUw_150.htm
    # http://appdb.winehq.org/objectManager.php?sClass=version&iId=7091
    try $WINE regsvr32 ir50_32.dll
}

#----------------------------------------------------------------
load_ie6() {
    load_msls31

    # Unregister Wine IE
    try $WINE iexplore -unregserver

    # Change the override to the native so we are sure we use and register them
    override_dlls native,builtin iexplore.exe itircl itss jscript mlang mshtml msimtf shdoclc shdocvw shlwapi urlmon

    # Remove the fake dlls, if any
    mv "$programfilesdir_unix"/"Internet Explorer"/iexplore.exe "$programfilesdir_unix"/"Internet Explorer"/iexplore.exe.bak
    for dll in itircl itss jscript mlang mshtml msimtf shdoclc shdocvw shlwapi urlmon
    do
        test -f "$WINDIR"/system32/$dll.dll &&
          mv "$WINDIR"/system32/$dll.dll "$WINDIR"/system32/$dll.dll.bak
    done

    # The installer doesn't want to install iexplore.exe in XP mode.
    set_winver win2k

    # Workaround a IE6 Installer bug, not Wine's fault
    # See http://bugs.winehq.org/show_bug.cgi?id=5409
    # Actual value downloaded doesn't matter
    rm -f "$WINETRICKS_CACHE"/ie6sites.dat
    download . http://www.microsoft.com/windows/ie/ie6sp1/download/rtw/x86/ie6sites.dat

    # Install
    download . http://download.microsoft.com/download/ie6sp1/finrel/6_sp1/W98NT42KMeXP/EN-US/ie6setup.exe f3ab61a785eb9611fa583612e83f3b69377f2cef
    
    # Workaround http://bugs.winehq.org/show_bug.cgi?id=21009
    # See also http://code.google.com/p/winezeug/issues/detail?id=78
    rm -f "$WINDIR"/system32/browseui.dll "$WINDIR"/system32/inseng.dll

    # Silent install recipe from:
    # http://www.axonpro.sk/japo/info/MS/SILENT%20INSTALL/Unattended-Silent%20Installation%20Switches%20for%20Windows%20Apps.htm
    if [ $WINETRICKS_QUIET ]
    then
        $WINE "$WINETRICKS_CACHE"/ie6setup.exe /q:a /r:n /c:"ie6wzd /S:""#e"" /q:a /r:n"
    else
        $WINE "$WINETRICKS_CACHE"/ie6setup.exe
    fi
    # IE6 exits with 194 to signal a reboot
    status=$?
    case $status in
    0|194) ;;
    *) die ie6 installation failed
    esac

    # Work around DLL registration bug until ierunonce/RunOnce/wineboot is fixed
    # FIXME: whittle down this list
    cd "$WINDIR"/system32/
    for i in actxprxy.dll browseui.dll browsewm.dll cdfview.dll ddraw.dll \
      dispex.dll dsound.dll iedkcs32.dll iepeers.dll iesetup.dll \
      imgutil.dll inetcomm.dll inseng.dll isetup.dll jscript.dll laprxy.dll \
      mlang.dll mshtml.dll mshtmled.dll msi.dll msident.dll \
      msoeacct.dll msrating.dll mstime.dll msxml3.dll occache.dll \
      ole32.dll oleaut32.dll olepro32.dll pngfilt.dll quartz.dll \
      rpcrt4.dll rsabase.dll rsaenh.dll scrobj.dll scrrun.dll \
      shdocvw.dll shell32.dll urlmon.dll vbscript.dll webcheck.dll \
      wshcon.dll wshext.dll asctrls.ocx hhctrl.ocx mscomct2.ocx \
      plugin.ocx proctexe.ocx tdc.ocx webcheck.dll wshom.ocx
    do
        $WINE regsvr32 /i $i > /dev/null 2>&1
    done

    # Set windows version back to user's default. Leave at win2k for better rendering (is there a bug for that?)
    unset_winver

    # try $WINE "$programfilesdir_unix"/"Internet Explorer"/IEXPLORE.EXE http://www.winehq.org
}

#----------------------------------------------------------------

load_ie7() {
    # Unregister Wine IE
    try $WINE iexplore -unregserver

    # Change the override to the native so we are sure we use and register them
    override_dlls native,builtin iexplore.exe itircl itss jscript mshtml msimtf shdoclc shdocvw shlwapi urlmon xmllite

    # Bundled updspapi cannot work on wine
    override_dlls builtin updspapi

    # Remove the fake dlls from the existing WINEPREFIX
    for dll in itircl itss jscript mshtml msimtf shdoclc shdocvw shlwapi urlmon
    do
        test -f "$WINDIR"/system32/$dll.dll &&
        mv "$WINDIR"/system32/$dll.dll "$WINDIR"/system32/$dll.dll.bak
    done

    # See http://bugs.winehq.org/show_bug.cgi?id=16013
    # Find instructions to create this file in dlls/wintrust/tests/crypt.c
    download . http://winezeug.googlecode.com/svn/trunk/winetricks_files/winetest.cat ac8f50dd54d011f3bb1dd79240dae9378748449f

    # Put a dummy catalog file in place
    mkdir -p "$WINDIR"/system32/catroot/\{f750e6c3-38ee-11d1-85e5-00c04fc295ee\}
    try cp -f "$WINETRICKS_CACHE"/winetest.cat "$WINDIR"/system32/catroot/\{f750e6c3-38ee-11d1-85e5-00c04fc295ee\}/oem0.cat

    # Install
    download . http://download.microsoft.com/download/3/8/8/38889DC1-848C-4BF2-8335-86C573AD86D9/IE7-WindowsXP-x86-enu.exe d39b89c360fbaa9706b5181ae4718100687a5326
    if [ $WINETRICKS_QUIET ]
    then
        $WINE "$WINETRICKS_CACHE"/IE7-WindowsXP-x86-enu.exe /quiet
    else
        $WINE "$WINETRICKS_CACHE"/IE7-WindowsXP-x86-enu.exe
    fi

    # Work around DLL registration bug until ierunonce/RunOnce/wineboot is fixed
    # FIXME: whittle down this list
    cd "$WINDIR"/system32/
    for i in actxprxy.dll browseui.dll browsewm.dll cdfview.dll ddraw.dll \
      dispex.dll dsound.dll iedkcs32.dll iepeers.dll iesetup.dll \
      imgutil.dll inetcomm.dll inseng.dll isetup.dll jscript.dll laprxy.dll \
      mlang.dll mshtml.dll mshtmled.dll msi.dll msident.dll \
      msoeacct.dll msrating.dll mstime.dll msxml3.dll occache.dll \
      ole32.dll oleaut32.dll olepro32.dll pngfilt.dll quartz.dll \
      rpcrt4.dll rsabase.dll rsaenh.dll scrobj.dll scrrun.dll \
      shdocvw.dll shell32.dll urlmon.dll vbscript.dll webcheck.dll \
      wshcon.dll wshext.dll asctrls.ocx hhctrl.ocx mscomct2.ocx \
      plugin.ocx proctexe.ocx tdc.ocx webcheck.dll wshom.ocx
    do
        $WINE regsvr32 /i $i > /dev/null 2>&1
    done

    # Seeing is believing
    if [ "$WINETRICKS_QUIET" = "" ]
    then
        warn "Starting ie7.  To start it later, use the command $WINE '${programfilesdir_win}\\\\Internet Explorer\\\\iexplore'"
        $WINE "${programfilesdir_win}\\Internet Explorer\\iexplore" http://www.microsoft.com/windows/internet-explorer/ie7/ > /dev/null 2>&1 &
    else
        warn "To start ie7, use the command $WINE '${programfilesdir_win}\\\\Internet Explorer\\\\iexplore'"
    fi
}

#----------------------------------------------------------------

load_jet40() {
    # http://support.microsoft.com/kb/239114
    # See also http://bugs.winehq.org/show_bug.cgi?id=6085
    download . http://download.microsoft.com/download/4/3/9/4393c9ac-e69e-458d-9f6d-2fe191c51469/jet40sp8_9xnt.exe 8cd25342030857969ede2d8fcc34f3f7bcc2d6d4
    try $WINE "$WINETRICKS_CACHE"/jet40sp8_9xnt.exe $WINETRICKS_QUIET
}

#----------------------------------------------------------------

load_kde() {
    download . http://www.winkde.org/pub/kde/ports/win32/installer/kdewin-installer-gui-0.9.6-5.exe f612945e094390d7bc0e4f8840d308ef2b00f86e
    mkdir -p "$programfilesdir_unix/kde"
    try cp "$WINETRICKS_CACHE"/kdewin-installer-gui-0.9.6-5.exe "$programfilesdir_unix/kde"
    cd "$programfilesdir_unix/kde"
    try $WINE "$programfilesdir_win\\kde\\kdewin-installer-gui-0.9.6-5.exe"
    cd "$olddir"
}

#----------------------------------------------------------------

load_liberation() {
    # http://www.redhat.com/promo/fonts/
    case `uname -s` in
    SunOS|Solaris)
      echo "If you get 'ERROR: Certificate verification error for fedorahosted.org: unable to get local issuer certificate':"
      echo "Then you need to add Verisign root certificates to your local keystore."
      echo "OpenSolaris users, see: http://www.linuxtopia.org/online_books/opensolaris_2008/SYSADV1/html/swmgrpatchtasks-14.html"
      echo "Or edit winetricks' download function, and add '--no-check-certificate' to the command."
      ;;
    esac

    download . https://fedorahosted.org/releases/l/i/liberation-fonts/liberation-fonts-1.04.tar.gz 097882c92e3260742a3dc3bf033792120d8635a3
    cd "$WINETRICKS_TMP"
    gunzip -dc "$WINETRICKS_CACHE"/liberation-fonts-1.04.tar.gz | tar -xf -
    mv liberation-fonts-1.04/*.ttf "$winefontsdir"
    rm -rf "$WINETRICKS_TMP"/*
    cd "$olddir"
}

#----------------------------------------------------------------

set_native_mdac() {
    # Set those overrides globally so user programs get MDAC's odbc
    # instead of wine's unixodbc
    override_dlls native,builtin odbc32 odbccp32 oledb32
}

#----------------------------------------------------------------

load_mdac25() {
    download mdac25 http://download.microsoft.com/download/e/e/4/ee4fe9ee-6fa1-4ab6-ab8c-fe1769f4edcf/mdac_typ.exe 09e974a5dbebaaa08c7985a4a1126886dc05fd87
    set_native_mdac
    set_winver nt40
    if [ $WINETRICKS_QUIET ]
    then
        try $WINE "$WINETRICKS_CACHE"/mdac25/mdac_typ.exe /q /C:"setup /QNT"
    else
        try $WINE "$WINETRICKS_CACHE"/mdac25/mdac_typ.exe
    fi
    unset_winver
}

#----------------------------------------------------------------

load_mdac27() {
    download mdac27 http://download.microsoft.com/download/3/b/f/3bf74b01-16ba-472d-9a8c-42b2b4fa0d76/mdac_typ.exe f68594d1f578c3b47bf0639c46c11c5da161feee
    set_native_mdac
    set_winver win2k
    if [ $WINETRICKS_QUIET ]
    then
        try $WINE "$WINETRICKS_CACHE"/mdac27/mdac_typ.exe /q /C:"setup /QNT"
    else
        try $WINE "$WINETRICKS_CACHE"/mdac27/mdac_typ.exe
    fi
    unset_winver
}

#----------------------------------------------------------------

load_mdac28() {
    download mdac28 http://download.microsoft.com/download/c/d/f/cdfd58f1-3973-4c51-8851-49ae3777586f/MDAC_TYP.EXE 91bd59f0b02b67f3845105b15a0f3502b9a2216a
    set_native_mdac
    set_winver win98
    if [ $WINETRICKS_QUIET ]
    then
        try $WINE "$WINETRICKS_CACHE"/mdac28/mdac_typ.exe /q /C:"setup /QNT"
    else
        try $WINE "$WINETRICKS_CACHE"/mdac28/mdac_typ.exe
    fi
    unset_winver
}

#----------------------------------------------------------------

load_mfc40() {
    # See http://support.microsoft.com/kb/122244
    download . http://download.microsoft.com/download/ole/ole2v/3.5/w351/en-us/ole2v.exe c6cac71f32405ccb09c6f375e0738e6e13f073e4
    try unzip -o $WINETRICKS_UNIXQUIET -d "$WINETRICKS_TMP" "$WINETRICKS_CACHE"/ole2v.exe
    try cp -f "$WINETRICKS_TMP"/MFC40.DLL "$WINDIR"/system32/

    rm -rf "$WINETRICKS_TMP"/*
}

#----------------------------------------------------------------

load_mingw_min() {
    # If this is just a dependency check, don't re-install
    if test $PACKAGE != mingw_min && test $PACKAGE != mingw && test $PACKAGE != mingw-min && test -f "$DRIVE_C"/MinGW/bin/gcc.exe
    then
        echo "prerequisite mingw_min already installed, skipping"
        return
    fi

    # See http://mingw.org/wiki/Getting_Started
    download . http://sourceforge.net/projects/mingw/files/GNU%20Binutils/binutils-2.19.1-mingw32-bin.tar.gz 1ab72f3af3fe96d08c3c9bff60c47913704d5774
    download . http://sourceforge.net/projects/mingw/files/GCC%20Version%204/gcc-core-4.4.0-mingw32-bin.tar.gz b88b8f3644ca0cdf2c41cd03f820bf7823a8eabb
    download . http://sourceforge.net/projects/mingw/files/GCC%20Version%204/gcc-core-4.4.0-mingw32-dll.tar.gz 0372ecf4caf75d0d9fe4a7739ca234f1a3de831b
    download . http://sourceforge.net/projects/mingw/files/GCC%20Version%204/gmp-4.2.4-mingw32-dll.tar.gz a14dd928382f093f67cb3cd57c140625b1b265bb
    download . http://sourceforge.net/projects/mingw/files/MinGW%20libiconv/libiconv-1.13.1-1-mingw32-dll-2.tar.lzma 5b60ce4d9ec9cf91aee437915a2469b915e1235f
    download . http://sourceforge.net/projects/mingw/files/MinGW%20Runtime/mingwrt-3.16-mingw32-dev.tar.gz 770ff5001989d8a9a1ec4f3621d8f264a24e178f
    download . http://sourceforge.net/projects/mingw/files/MinGW%20Runtime/mingwrt-3.16-mingw32-dll.tar.gz b8032e97c79e16a3c540043f0f39821df1531ae9
    download . http://sourceforge.net/projects/mingw/files/GCC%20Version%204/mpfr-2.4.1-mingw32-dll.tar.gz 43b7ecb2c0c785c44321ff6c4376f51375713a7b
    download . http://sourceforge.net/projects/mingw/files/GCC%20Version%204/pthreads-w32-2.8.0-mingw32-dll.tar.gz f922f8c0c42921fd4482a3d2e6f779d6384040c1
    download . http://sourceforge.net/projects/mingw/files/MinGW%20API%20for%20MS-Windows/w32api-3.13-mingw32-dev.tar.gz 5eb7d8ec0fe032a92bea3a2c8282a78df2f1793c

    mkdir "$DRIVE_C"/MinGW
    cd "$DRIVE_C"/MinGW
    gzip -d -c "$WINETRICKS_CACHE"/binutils-2.19.1-mingw32-bin.tar.gz | tar x
    gzip -d -c "$WINETRICKS_CACHE"/mingwrt-3.16-mingw32-dev.tar.gz | tar x
    gzip -d -c "$WINETRICKS_CACHE"/mingwrt-3.16-mingw32-dll.tar.gz | tar x
    gzip -d -c "$WINETRICKS_CACHE"/w32api-3.13-mingw32-dev.tar.gz | tar x
    gzip -d -c "$WINETRICKS_CACHE"/gmp-4.2.4-mingw32-dll.tar.gz | tar x
    lzma -d -c "$WINETRICKS_CACHE"/libiconv-1.13.1-1-mingw32-dll-2.tar.lzma | tar x
    gzip -d -c "$WINETRICKS_CACHE"/mpfr-2.4.1-mingw32-dll.tar.gz | tar x
    gzip -d -c "$WINETRICKS_CACHE"/pthreads-w32-2.8.0-mingw32-dll.tar.gz | tar x
    gzip -d -c "$WINETRICKS_CACHE"/gcc-core-4.4.0-mingw32-bin.tar.gz | tar x
    gzip -d -c "$WINETRICKS_CACHE"/gcc-core-4.4.0-mingw32-dll.tar.gz | tar x

    append_path C:\\MinGW\\bin
}

#----------------------------------------------------------------

load_mingw_gdb() {
    # See http://mingw.org/wiki/Getting_Started
    load_mingw_min

    download . $SOURCEFORGE/mingw/GNU%20Source-Level%20Debugger/GDB-7.0/gdb-7.0-2-mingw32-bin.tar.gz a560cb0e3980d0ed853994c84038260212f58925

    cd "$DRIVE_C"/MinGW
    gzip -d -c "$WINETRICKS_CACHE"/gdb-7.0-2-mingw32-bin.tar.gz | tar x
}

#----------------------------------------------------------------

load_mono20() {
    # Load Mono, have it handle all .net requests
    download .  ftp://ftp.novell.com/pub/mono/archive/2.0.1/windows-installer/1/mono-2.0.1-gtksharp-2.10.4-win32-1.exe ccb67ac41b59522846e47d0c423836b9d334c088
    try $WINE "$WINETRICKS_CACHE"/mono-2.0.1-gtksharp-2.10.4-win32-1.exe $WINETRICKS_SILENT

    cat > "$WINETRICKS_TMP"/mono_2.0.reg <<_EOF_
REGEDIT4

[HKEY_LOCAL_MACHINE\Software\Microsoft\NET Framework Setup\NDP\v2.0.50727]
"Install"=dword:00000001
"SP"=dword:00000001

[HKEY_LOCAL_MACHINE\Software\Microsoft\.NETFramework\policy\v2.0]
"4322"="3706-4322"
_EOF_
    try_regedit "$WINETRICKS_TMP"/mono_2.0.reg
    rm -f "$WINETRICKS_TMP"/mono_2.0.reg
}

#----------------------------------------------------------------

load_mono22() {
    # Load Mono, have it handle all .net requests
    download .  ftp://ftp.novell.com/pub/mono/archive/2.2/windows-installer/5/mono-2.2-gtksharp-2.12.7-win32-5.exe be977dfa9c49deea1be02ba4a2228e343f1e5840
    try $WINE "$WINETRICKS_CACHE"/mono-2.2-gtksharp-2.12.7-win32-5.exe $WINETRICKS_SILENT

    # FIXME: what should this be for mono 2.2?
    cat > "$WINETRICKS_TMP"/mono_2.0.reg <<_EOF_
REGEDIT4

[HKEY_LOCAL_MACHINE\Software\Microsoft\NET Framework Setup\NDP\v2.0.50727]
"Install"=dword:00000001
"SP"=dword:00000001

[HKEY_LOCAL_MACHINE\Software\Microsoft\.NETFramework\policy\v2.0]
"4322"="3706-4322"
_EOF_
    try_regedit "$WINETRICKS_TMP"/mono_2.0.reg
    rm -f "$WINETRICKS_TMP"/mono_2.0.reg
}

#----------------------------------------------------------------

load_mono24() {
    # Load Mono, have it handle all .net requests
    download .  http://ftp.novell.com/pub/mono/archive/2.4.2.3/windows-installer/3/mono-2.4.2.3-gtksharp-2.12.9-win32-3.exe 4f0d051bcedd7668e63c12903310be0ea38f9654
    try $WINE "$WINETRICKS_CACHE"/mono-2.4.2.3-gtksharp-2.12.9-win32-3.exe $WINETRICKS_SILENT

    # FIXME: what should this be for mono 2.4?
    cat > "$WINETRICKS_TMP"/mono_2.0.reg <<_EOF_
REGEDIT4

[HKEY_LOCAL_MACHINE\Software\Microsoft\NET Framework Setup\NDP\v2.0.50727]
"Install"=dword:00000001
"SP"=dword:00000001

[HKEY_LOCAL_MACHINE\Software\Microsoft\.NETFramework\policy\v2.0]
"4322"="3706-4322"
_EOF_
    try_regedit "$WINETRICKS_TMP"/mono_2.0.reg
    rm -f "$WINETRICKS_TMP"/mono_2.0.reg
}

#----------------------------------------------------------------

load_mozillabuild() {
    download . http://ftp.mozilla.org/pub/mozilla.org/mozilla/libraries/win32/MozillaBuildSetup-1.4.exe
    try $WINE "$WINETRICKS_CACHE"/MozillaBuildSetup-1.4.exe $WINETRICKS_S
}

#----------------------------------------------------------------

load_mpc() {
    download . $SOURCEFORGE/guliverkli2/Media%20Player%20Classic/6.4.9.1/mplayerc_20090706.zip 9b8e06a3997a786dccfb5739e0cc1a34f17905b6
    mkdir -p "$programfilesdir_unix/Media Player Classic"
    cd "$programfilesdir_unix/Media Player Classic"
    try unzip "$WINETRICKS_CACHE"/mplayerc_20090706.zip

    append_path "$programfilesdir_win\Media Player Classic"

    cd "$olddir"
    warn "MPC now available as $programfilesdir_win\Media Player Classic\mplayerc.exe"
}

#----------------------------------------------------------------

load_msi2() {
    # Install native msi per http://wiki.winehq.org/NativeMsi
    # http://www.microsoft.com/downloads/details.aspx?displaylang=en&FamilyID=CEBBACD8-C094-4255-B702-DE3BB768148F
    download . http://download.microsoft.com/download/WindowsInstaller/Install/2.0/W9XMe/EN-US/InstMsiA.exe e739c40d747e7c27aacdb07b50925b1635ee7366

    # Pick win98 so we can install native msi
    set_winver win98

    # Avoid "err:setupapi:SetupDefaultQueueCallbackA copy error 5 ..."
    rm -f "$WINDIR"/system32/msi.dll
    rm -f "$WINDIR"/system32/msiexec.exe

    WINEDLLOVERRIDES="msi,msiexec.exe=n" try $WINE "$WINETRICKS_CACHE"/InstMSIA.exe $WINETRICKS_QUIET

    override_dlls native,builtin msi msiexec.exe

    # and undo version win98
    unset_winver
}

#----------------------------------------------------------------

load_mshflxgd() {
    # http://msdn.microsoft.com/en-us/library/aa240864(VS.60).aspx
    # orig: 5f9c7a81022949bfe39b50f2bbd799c448bb7377
    # Jan 2009: 7ad74e589d5eefcee67fa14e65417281d237a6b6
    # May 2009: bd8aa796e16e5f213414af78931e0379d9cbe292
    download .  http://activex.microsoft.com/controls/vb6/MSHFLXGD.CAB bd8aa796e16e5f213414af78931e0379d9cbe292
    try cabextract --directory="$WINETRICKS_TMP" "$WINETRICKS_CACHE"/MSHFLXGD.CAB
    try cp -f "$WINETRICKS_TMP"/[Mm][Ss][Hh][Ff][Ll][Xx][Gg][Dd].[Oo][Cc][Xx] "$WINDIR"/system32
}

#----------------------------------------------------------------

load_msls31() {
    # Install native Microsoft Line Services (needed by e-Sword, possibly only when using native riched20)
    download . http://download.microsoft.com/download/WindowsInstaller/Install/2.0/W9XMe/EN-US/InstMsiA.exe e739c40d747e7c27aacdb07b50925b1635ee7366
    try cabextract --directory="$WINETRICKS_TMP" "$WINETRICKS_CACHE"/InstMsiA.exe
    try cp -f "$WINETRICKS_TMP"/msls31.dll "$WINDIR"/system32
}

#----------------------------------------------------------------

load_msmask() {
    # http://msdn.microsoft.com/en-us/library/11405hcf(VS.71).aspx
    # http://bugs.winehq.org/show_bug.cgi?id=2934
    # old: 3c6b26f68053364ea2e09414b615dbebafb9d5c3
    # May 2009: 30e55679e4a13fe4d9620404476f215f93239292
    download .  http://activex.microsoft.com/controls/vb6/MSMASK32.CAB 30e55679e4a13fe4d9620404476f215f93239292
    try cabextract --directory="$WINETRICKS_TMP" "$WINETRICKS_CACHE"/MSMASK32.CAB
    try cp -f "$WINETRICKS_TMP"/[Mm][Ss][Mm][Aa][Ss][Kk]32.[Oo][Cc][Xx] "$WINDIR"/system32/msmask32.ocx
    try $WINE regsvr32 msmask32.ocx
}

#----------------------------------------------------------------

load_msscript() {
    # http://msdn.microsoft.com/scripting/scriptcontrol/x86/sct10en.exe
    # http://www.microsoft.com/downloads/details.aspx?familyid=d7e31492-2595-49e6-8c02-1426fec693ac
    download .  http://download.microsoft.com/download/d/2/a/d2a7430c-6d5b-48e9-96c4-3c751be7bffe/sct10en.exe fd9f2f23357ab11ae70682d6864f7e9f188adf2a
    try cabextract --directory="$WINETRICKS_TMP" "$WINETRICKS_CACHE"/sct10en.exe
    try cp -f "$WINETRICKS_TMP"/msscript.ocx "$WINDIR"/system32
    try $WINE regsvr32 msscript.ocx
}

#----------------------------------------------------------------

load_msxml3() {
    # Service Pack 5
    #download http://download.microsoft.com/download/a/5/e/a5e03798-2454-4d4b-89a3-4a47579891d8/msxml3.msi
    # Service Pack 7
    download . http://download.microsoft.com/download/8/8/8/888f34b7-4f54-4f06-8dac-fa29b19f33dd/msxml3.msi d4c2178dfb807e1a0267fce0fd06b8d51106d913
    # http://bugs.winehq.org/show_bug.cgi?id=7849 fixed since 0.9.37
    override_dlls native,builtin msxml3
    try $WINE msiexec /i "$WINETRICKS_CACHE"/msxml3.msi $WINETRICKS_QUIET
}

#----------------------------------------------------------------

load_msxml4() {
    # If this is just a dependency check, don't re-install
    if test $PACKAGE != msxml4 && test -f "$WINDIR"/system32/msxml4.dll
    then
        echo "prerequisite msxml4 already installed, skipping"
        return
    fi
    # MS06-071: http://www.microsoft.com/downloads/details.aspx?familyid=24B7D141-6CDF-4FC4-A91B-6F18FE6921D4
    # download . http://download.microsoft.com/download/e/2/e/e2e92e52-210b-4774-8cd9-3a7a0130141d/msxml4-KB927978-enu.exe d364f9fe80c3965e79f6f64609fc253dfeb69c25
    # MS07-042: http://www.microsoft.com/downloads/details.aspx?FamilyId=021E12F5-CB46-43DF-A2B8-185639BA2807
    # download . http://download.microsoft.com/download/9/4/2/9422e6b6-08ee-49cb-9f05-6c6ee755389e/msxml4-KB936181-enu.exe 73d75d7b41f8a3d49f272e74d4f73bb5e82f1acf
    # SP3 (2009): http://www.microsoft.com/downloads/details.aspx?familyid=7F6C0CB4-7A5E-4790-A7CF-9E139E6819C0
    download msxml4sp3 http://download.microsoft.com/download/A/2/D/A2D8587D-0027-4217-9DAD-38AFDB0A177E/msxml.msi aa70c5c1a7a069af824947bcda1d9893a895318b

    try $WINE msiexec /i "$WINETRICKS_CACHE"/msxml4sp3/msxml.msi $WINETRICKS_QUIET
}

#----------------------------------------------------------------

load_msxml6() {
    # If this is just a dependency check, don't re-install
    if test $PACKAGE != msxml6 && test -f "$WINDIR"/system32/msxml6.dll
    then
        echo "prerequisite msxml6 already installed, skipping"
        return
    fi

    # http://www.microsoft.com/downloads/details.aspx?FamilyID=993c0bcf-3bcf-4009-be21-27e85e1857b1
    # download . http://download.microsoft.com/download/2/e/0/2e01308a-e17f-4bf9-bf48-161356cf9c81/msxml6.msi 2308743ddb4cb56ae910e461eeb3eab0a9e58058
    # Service Pack 1
    # http://www.microsoft.com/downloads/details.aspx?familyid=D21C292C-368B-4CE1-9DAB-3E9827B70604
    download . http://download.microsoft.com/download/e/a/f/eafb8ee7-667d-4e30-bb39-4694b5b3006f/msxml6_x86.msi

    try $WINE msiexec /i "$WINETRICKS_CACHE"/msxml6_x86.msi $WINETRICKS_QUIET
}

#----------------------------------------------------------------

load_ogg() {
    # flac, ogg, speex, vorbis, ogm source, ogg source
    download . http://cross-lfs.org/~mlankhorst/oggcodecs_0.81.2.exe c9d10a8f1b65b9f3824e227333d66247e14fad4c
    #try $WINE "$WINETRICKS_CACHE"/oggcodecs_0.81.2.exe $WINETRICKS_QUIET
    # oh, and the new schroedinger direct show filter, too
    # see following URLs for more info
    # http://www.diracvideo.org/
    # http://cross-lfs.org/~mlankhorst/direct-schro.txt
    # http://www.diracvideo.org/git?p=direct-schro.git;a=summary
    # Requires wine-1.1.1
    download . http://cross-lfs.org/~mlankhorst/direct-schro.dll
    try cp "$WINETRICKS_CACHE"/direct-schro.dll "$WINDIR"/system32/direct-schro.dll
    # This is currently broken. Maarten's not sure why.
    try $WINE regsvr32 direct-schro.dll
}

#----------------------------------------------------------------

load_ole2() {
    # http://support.microsoft.com/kb/123087/EN-US/
    download . http://download.microsoft.com/download/win31/update/2.03/win/en-us/ww1116.exe b803991c40f387464b61f606536b7c98a88245d2
    try unzip -o $WINETRICKS_UNIXQUIET -d "$WINETRICKS_TMP" "$WINETRICKS_CACHE"/ww1116.exe
    set_winver win31
    cd "$WINETRICKS_TMP"
    try $WINE setup.exe
    cd "$olddir"
    unset_winver

    override_dlls native,builtin COMPOBJ.DLL OLE2CONV.DLL OLE2DISP.DLL OLE2.DLL
    override_dlls native,builtin OLE2NLS.DLL OLE2PROX.DLL STORAGE.DLL TYPELIB.DLL
}

#----------------------------------------------------------------

load_openwatcom() {
    # http://www.openwatcom.org
    download . "http://ftp.openwatcom.org/ftp/open-watcom-c-win32-1.8.exe" 44afd1fabfdf0374f614f054824e60ac560f9dc0
    if [ $WINETRICKS_QUIET ]
    then
        # Options documented at http://bugzilla.openwatcom.org/show_bug.cgi?id=898
        # But they don't seem to work on wine, so jam them into setup.inf
        # Pick smallest installation that supports 16 bit C and C++
        cd "$WINETRICKS_TMP"
        cp "$WINETRICKS_CACHE"/open-watcom-c-win32-1.8.exe .
        unzip open-watcom-c-win32-1.8.exe setup.inf
        sed -i 's/tools16=.*/tools16=true/' setup.inf
        zip -f open-watcom-c-win32-1.8.exe
        try $WINE open-watcom-c-win32-1.8.exe -s
        cd "$olddir"
    else
        try $WINE "$WINETRICKS_CACHE"/open-watcom-c-win32-1.8.exe
    fi
    if test ! -f "$DRIVE_C"/WATCOM/binnt/wcc.exe
    then
        warn "c:/watcom/binnt/wcc.exe not found; you probably didn't select 16 bit tools, and won't be able to buld win16test"
    fi
}

#----------------------------------------------------------------

load_pdh() {
    # http://support.microsoft.com/kb/284996
    download . http://download.microsoft.com/download/platformsdk/Redist/5.0.2195.2668/NT4/EN-US/pdhinst.exe f42448660def8cd7f42b34aa7bc7264745f4425e
    try cabextract --directory="$WINETRICKS_TMP" "$WINETRICKS_CACHE"/pdhinst.exe
    try unzip -o $WINETRICKS_UNIXQUIET -d "$WINETRICKS_TMP" "$WINETRICKS_TMP"/pdh.exe
    try cp -f "$WINETRICKS_TMP"/x86/Pdh.Dll "$WINDIR"/system32/pdh.dll
}

#----------------------------------------------------------------

load_physx()
{
    # http://www.nvidia.com/object/physx_9.09.0814.html
    download . http://us.download.nvidia.com/Windows/9.09.0814/PhysX_9.09.0814_SystemSoftware.exe e19f7c3385a4a68e7acb85301bb4d2d0d1eaa1e2
    try $WINE "$WINETRICKS_CACHE"/PhysX_9.09.0814_SystemSoftware.exe $WINETRICKS_QUIET
}

#----------------------------------------------------------------

load_psdk2003()
{
    load_vcrun6

    # Note: aborts on 64 bit windows with dialog saying "don't run on WoW"
    # http://www.microsoft.com/downloads/details.aspx?familyid=0baf2b35-c656-4969-ace8-e4c0c0716adb
    download psdk2003 http://download.microsoft.com/download/f/a/d/fad9efde-8627-4e7a-8812-c351ba099151/PSDK-x86.exe 5c7dc2e1eb902b376d7797cc383fefdfc64ff9c9
    echo "This can take up to an hour."
    cd "$WINETRICKS_CACHE"/psdk2003
    try $WINE PSDK-x86.exe
    cd "$olddir"
}

#----------------------------------------------------------------

load_psdkvista()
{
    # http://www.microsoft.com/downloads/details.aspx?familyid=0baf2b35-c656-4969-ace8-e4c0c0716adb
    warn "Vista SDK doesn't work yet as of wine-1.1.28"
    load_dotnet20
    download psdkvista download.microsoft.com/download/c/a/1/ca145d10-e254-475c-85f9-1439f4cd2a9e/Setup.exe 756c21a7fc9b831f7200f3f44ae55cc7689e8063
    #chmod +x "$WINETRICKS_CACHE"/psdkvista/Setup.exe
    cd "$WINETRICKS_CACHE"/psdkvista
    try $WINE Setup.exe
    cd "$olddir"
}

#----------------------------------------------------------------

load_psdkwin7()
{
    # http://www.microsoft.com/downloads/details.aspx?FamilyID=c17ba869-9671-4330-a63e-1fd44e0e2505&displaylang=en
    warn "When given a choice, select only C++ compilers and headers, the other options don't work yet."
    load_vcrun6
    load_vcrun2008
    load_dotnet20

    # don't have a working unattended recipe.  Maybe we'll have to
    # do an autohotkey script until msft gets its act together:
    # http://social.msdn.microsoft.com/Forums/en-US/windowssdk/thread/c053b616-7d5b-405d-9841-ec465a8e21d5
    download psdkwin7 http://download.microsoft.com/download/7/A/B/7ABD2203-C472-4036-8BA0-E505528CCCB7/winsdk_web.exe a01dcc67a38f461e80ea649edf1353f306582507
    cd "$WINETRICKS_CACHE"/psdkwin7
    try $WINE winsdk_web.exe
    cd "$olddir"
}

#----------------------------------------------------------------

load_python26() {

    # If this is just a dependency check, don't re-install
    if test $PACKAGE != python26 && test -f "$WINEPREFIX"/drive_c/Python26/python.exe
    then
        echo "prerequisite python26 already installed, skipping"
        return
    fi

    download . http://www.python.org/ftp/python/2.6.2/python-2.6.2.msi 2d1503b0e8b7e4c72a276d4d9027cf4856b208b8
    download . $SOURCEFORGE/project/pywin32/pywin32/Build%20214/pywin32-214.win32-py2.6.exe eca58f29b810d8e3e7951277ebb3e35ac35794a3
    cd "$WINETRICKS_CACHE"
    try $WINE msiexec /i python-2.6.2.msi ALLUSERS=1 $WINETRICKS_QUIET
    # FIXME: unzip this instead of running it if quiet install?
    try $WINE pywin32-214.win32-py2.6.exe
    cd "$olddir"
}

#----------------------------------------------------------------

load_python_comtypes() {
    
    load_python26
    
    download . $SOURCEFORGE/project/comtypes/comtypes/0.6.1/comtypes-0.6.1-1.zip 814318cdae0ab2471a9cd500847bf12f4df9a57c
    cd "$WINETRICKS_CACHE"
    try unzip comtypes-0.6.1-1.zip
    cd comtypes-0.6.1
    try $WINE "C:\Python26\python.exe" setup.py install
    cd "$olddir"
}

#----------------------------------------------------------------

load_quicktime72() {
    echo "Quicktime needs gdiplus..."
    load_gdiplus

    # http://www.apple.com/support/downloads/quicktime72forwindows.html
    download quicktime72 'http://wsidecar.apple.com/cgi-bin/nph-reg3rdpty2.pl/product=14402&cat=59&platform=osx&method=sa/QuickTimeInstaller.exe' bb89981f10cf21de57b9453e53cf81b9194271a9
    unset QUICKTIME_QUIET
    if test "$WINETRICKS_QUIET"x != x
    then
       QUICKTIME_QUIET="/qn"  # ISSETUPDRIVEN=0
    fi
    # set vista mode to inhibit directdraw overlay use that blacks the screen
    set_winver vista
    try $WINE "$WINETRICKS_CACHE"/quicktime72/QuickTimeInstaller.exe ALLUSERS=1 DESKTOP_SHORTCUTS=0 QTTaskRunFlags=0 QTINFO.BISQTPRO=1 SCHEDULE_ASUW=0 REBOOT_REQUIRED=No $QUICKTIME_QUIET > /dev/null 2>&1
    if test "$WINETRICKS_QUIET"x = x
    then
        echo "You probably want to select Advanced / Safe Mode in the Quicktime control panel"
        try $WINE control "$programfilesdir_win\\QuickTime\\QTSystem\\QuickTime.cpl"
    fi

    unset_winver
    # user might want to set vista mode himself, or run
    #  wine control ".wine/drive_c/Program Files/QuickTime/QTSystem/QuickTime.cpl"
    # and pick Advanced / Safe Mode (gdi only).
    # We could probably force that by overwriting QuickTime.qtp
    # (probably in Program Files/QuickTime/QTSystem/QuickTime.qtp)
    # but the format isn't known, so we'd have to override all other settings, too.
}

#----------------------------------------------------------------

volnum() {
    case "$OS" in
    "Windows_NT")
      return
      ;;
    esac

    # Recent Microsoft installers are often based on "windows package manager", see
    # http://support.microsoft.com/kb/262841 and
    # http://www.microsoft.com/technet/prodtechnol/windowsserver2003/deployment/winupdte.mspx
    # These installers check the drive name, and if it doesn't start with 'harddisk',
    # they complain "Unable to find a volume for file extraction", see
    # http://bugs.winehq.org/show_bug.cgi?id=5351
    # You may be able to work around this by using the installer's /x or /extract switch,
    # but renaming drive_c to "harddiskvolume0" lets you just run the installer as normal.

    if test ! -d "$WINEPREFIX"/harddiskvolume0/
    then
        ln -s drive_c "$WINEPREFIX"/harddiskvolume0
        rm "$WINEPREFIX"/dosdevices/c:
        ln -s ../harddiskvolume0 "$WINEPREFIX"/dosdevices/c:
        echo "Renamed drive_c to harddiskvolume0"
    else
        echo "drive_c already named harddiskvolume0"
    fi
}

#----------------------------------------------------------------

load_riched20() {
    # http://support.microsoft.com/?kbid=249973
    download . http://download.microsoft.com/download/winntsp/Patch/RTF/NT4/EN-US/Q249973i.EXE f0b7663f15dbd31410435483ba832318c7a70470
    try cabextract --directory="$WINETRICKS_TMP" "$WINETRICKS_CACHE"/Q249973i.EXE
    try cp -f "$WINETRICKS_TMP"/riched??.dll "$WINDIR"/system32
    override_dlls native,builtin riched20 riched32

    rm -rf "$WINETRICKS_TMP"/*
}

#----------------------------------------------------------------

load_riched30() {
    # http://www.novell.com/documentation/nm1/readmeen_web/readmeen_web.html#Akx3j64
    # claims that Groupwise Messenger's View / Text Size command
    # only works with riched30, and recommends getting it by installing
    # msi 2, which just happens to come with riched30 version of riched20
    # (though not with a corresponding riched32, which might be a problem)
    # http://www.microsoft.com/downloads/details.aspx?displaylang=en&FamilyID=CEBBACD8-C094-4255-B702-DE3BB768148F
    download . http://download.microsoft.com/download/WindowsInstaller/Install/2.0/W9XMe/EN-US/InstMsiA.exe e739c40d747e7c27aacdb07b50925b1635ee7366
    try cabextract --directory="$WINETRICKS_TMP" "$WINETRICKS_CACHE"/InstMsiA.exe
    try cp -f "$WINETRICKS_TMP"/riched20.dll "$WINDIR"/system32
    override_dlls native,builtin riched20

    rm -rf "$WINETRICKS_TMP"/*
}

#----------------------------------------------------------------

load_richtx32() {
    download . http://activex.microsoft.com/controls/vb6/richtx32.cab da404b566df3ad74fe687c39404a36c3e7cadc07
    try cabextract "$WINETRICKS_CACHE"/richtx32.cab -d "$WINDIR"/system32 -F RichTx32.Ocx
    try $WINE regsvr32 RichTx32.ocx
}

#----------------------------------------------------------------

load_shockwave() {
    # Not silent enough, use msi instead
    #download . http://fpdownload.macromedia.com/get/shockwave/default/english/win95nt/latest/Shockwave_Installer_Full.exe 840e34e9b067cf247bfa9092665b8966158f38e3
    #try $WINE "$WINETRICKS_CACHE"/Shockwave_Installer_Full.exe $WINETRICKS_S
    # old sha1sum: 6a91a9da4b54c3fdc97130a15e1a173117e5f4ff
    # 2009-07-31 sha1sum: 0bb506ef67a268e8d3fb6c7ce556320ee10b9da5
    # 2009-12-13 sha1sum: d35649883bf13cb1a86f5650e1050d15533ac0f4
    
    download . http://fpdownload.macromedia.com/get/shockwave/default/english/win95nt/latest/sw_lic_full_installer.msi d35649883bf13cb1a86f5650e1050d15533ac0f4
    try $WINE msiexec /i "$WINETRICKS_CACHE"/sw_lic_full_installer.msi $WINETRICKS_QUIET
}

#----------------------------------------------------------------

load_tahoma() {
    # The tahoma and tahomabd fonts are needed by e.g. Steam

    download . http://download.microsoft.com/download/office97pro/fonts/1/w95/en-us/tahoma32.exe 888ce7b7ab5fd41f9802f3a65fd0622eb651a068
    try cabextract --directory="$WINETRICKS_TMP" "$WINETRICKS_CACHE"/tahoma32.exe
    try cp -f "$WINETRICKS_TMP"/Tahoma.TTF "$winefontsdir"/tahoma.ttf
    try cp -f "$WINETRICKS_TMP"/Tahomabd.TTF "$winefontsdir"/tahomabd.ttf
    chmod +w "$winefontsdir"/tahoma*.ttf
    rm -rf "$WINETRICKS_TMP"/*
}

#----------------------------------------------------------------

load_urlmon() {
    # This is an updated urlmon from IE 6.0
    # See http://www.microsoft.com/downloads/details.aspx?familyid=85BB441A-5BB1-4A82-86EC-A249AF287513
    # (Works for Dolphin Smalltalk, see http://bugs.winehq.org/show_bug.cgi?id=8258)
    download . http://download.microsoft.com/download/8/2/0/820faffc-3ea0-4914-bca3-584235964ded/Q837251.exe bcc79b92ac3c06c4de3692672c3d70bdd36be892
    try cabextract --directory="$WINETRICKS_TMP" "$WINETRICKS_CACHE/Q837251.exe"
    try cp -f "$WINETRICKS_TMP"/URLMON.DLL "$WINDIR"/system32/urlmon.dll
    override_dlls native,builtin urlmon
}

#----------------------------------------------------------------

load_vb2run() {
    # Not referenced on MS web anymore. But the old Microsoft Software Library FTP still has it.
    # See ftp://ftp.microsoft.com/Softlib/index.txt
    download . ftp://ftp.microsoft.com/Softlib/MSLFILES/VBRUN200.EXE ac0568b73ee375408778e9b505df995f79ab907e
    try unzip -o $WINETRICKS_UNIXQUIET -d "$WINETRICKS_TMP" "$WINETRICKS_CACHE"/VBRUN200.EXE
    try cp -f "$WINETRICKS_TMP/VBRUN200.DLL" "$WINDIR"/system32/

}

#----------------------------------------------------------------

load_vb3run() {
    # See http://support.microsoft.com/kb/196285
    download . http://download.microsoft.com/download/vb30/utility/1/w9xnt4/en-us/vb3run.exe 518fcfefde9bf680695cadd06512efadc5ac2aa7
    try unzip -o $WINETRICKS_UNIXQUIET -d "$WINETRICKS_TMP" "$WINETRICKS_CACHE"/vb3run.exe
    try cp -f "$WINETRICKS_TMP/Vbrun300.dll" "$WINDIR"/system32/

}

#----------------------------------------------------------------

load_vb4run() {
    # See http://support.microsoft.com/kb/196286
    download . http://download.microsoft.com/download/vb40ent/sample27/1/w9xnt4/en-us/vb4run.exe 83e968063272e97bfffd628a73bf0ff5f8e1023b
    try unzip -o $WINETRICKS_UNIXQUIET -d "$WINETRICKS_TMP" "$WINETRICKS_CACHE"/vb4run.exe
    try cp -f "$WINETRICKS_TMP/Vb40032.dll" "$WINDIR"/system32/
    try cp -f "$WINETRICKS_TMP/Vb40016.dll" "$WINDIR"/system32/

}

#----------------------------------------------------------------

load_vbvm50() {
    download . http://download.microsoft.com/download/vb50pro/utility/1/win98/en-us/msvbvm50.exe 28bfaf09b8ac32cf5ffa81252f3e2fadcb3a8f27
    try $WINE "$WINETRICKS_CACHE"/msvbvm50.exe $WINETRICKS_QUIET
}

#----------------------------------------------------------------

load_vbrun60() {
    if test ! -f "$WINETRICKS_CACHE"/vbrun60sp6.exe
    then
        download . http://download.microsoft.com/download/5/a/d/5ad868a0-8ecd-4bb0-a882-fe53eb7ef348/VB6.0-KB290887-X86.exe 73ef177008005675134d2f02c6f580515ab0d842
        rm -rf "$WINETRICKS_TMP"/*

        try $WINE "$WINETRICKS_CACHE"/VB6.0-KB290887-X86.exe "/T:$winetricks_tmp_win" /c $WINETRICKS_QUIET
        if test ! -f "$WINETRICKS_TMP"/vbrun60sp6.exe
        then
            die vbrun60sp6.exe not found
        fi
        try mv "$WINETRICKS_TMP"/vbrun60sp6.exe "$WINETRICKS_CACHE"
    fi

    # Delete some fake DLLs to ensure that the installer overwrites them.
    rm -f "$WINDIR"/system32/comcat.dll
    rm -f "$WINDIR"/system32/oleaut32.dll
    rm -f "$WINDIR"/system32/olepro32.dll
    rm -f "$WINDIR"/system32/stdole2.tlb
    # Exits with status 43 for some reason?
    $WINE "$WINETRICKS_CACHE"/vbrun60sp6.exe $WINETRICKS_QUIET

    status=$?
    case $status in
    0|43) ;;
    *) die $PACKAGE installation failed
    esac
}

#----------------------------------------------------------------

load_vcrun6() {
    # Load the Visual C++ 6 runtime libraries, including the elusive mfc42u.dll

    # If this is just a dependency check, don't re-install
    if test $PACKAGE != vcrun6 && test -f "$WINDIR"/system32/mfc42u.dll
    then
        echo "prerequisite vcrun6 already installed, skipping"
        return
    fi

    if test ! -f "$WINETRICKS_CACHE"/vcredist.exe
    then
       download . http://download.microsoft.com/download/vc60pro/update/1/w9xnt4/en-us/vc6redistsetup_enu.exe 382c8f5a7f41189af8d4165cf441f274b7e2a457
       rm -rf "$WINETRICKS_TMP"/*

       try $WINE "$WINETRICKS_CACHE"/vc6redistsetup_enu.exe "/T:$winetricks_tmp_win" /c $WINETRICKS_QUIET
       if test ! -f "$WINETRICKS_TMP"/vcredist.exe
       then
          die vcredist.exe not found
       fi
       mv "$WINETRICKS_TMP"/vcredist.exe "$WINETRICKS_CACHE"
    fi
    # Delete some fake dlls to avoid vcredist installer warnings
    rm -f "$WINDIR"/system32/comcat.dll
    rm -f "$WINDIR"/system32/msvcrt.dll
    rm -f "$WINDIR"/system32/oleaut32.dll
    rm -f "$WINDIR"/system32/olepro32.dll
    rm -f "$WINDIR"/system32/stdole2.tlb
    # vcredist still exits with status 43.  Anyone know why?
    $WINE "$WINETRICKS_CACHE"/vcredist.exe

    status=$?
    case $status in
    0|43) ;;
    *) die $PACKAGE installation failed
    esac

    # And then some apps need mfc42u.dll, dunno what right way
    # is to get it, vcredist doesn't install it by default?
    try cabextract "$WINETRICKS_CACHE"/vcredist.exe -d "$WINDIR"/system32/ -F mfc42u.dll

    override_dlls native,builtin msvcrt

}

#----------------------------------------------------------------

load_vcrun6sp6() {
    if test ! -f vcredistsp6.exe
    then
        download . http://download.microsoft.com/download/1/9/f/19fe4660-5792-4683-99e0-8d48c22eed74/Vs6sp6.exe 2292437a8967349261c810ae8b456592eeb76620

        # No EULA is presented when passing command-line extraction arguments,
        # so we'll simplify extraction with cabextract. We'll also try to avoid
        # overwriting the older vcrun6 redist by renaming the extracted file.
        try cabextract "$WINETRICKS_CACHE"/Vs6sp6.exe -d "$WINETRICKS_TMP" -F vcredist.exe
        try mv "$WINETRICKS_TMP"/vcredist.exe "$WINETRICKS_CACHE"/vcredistsp6.exe
    fi

    # Delete some fake dlls to avoid vcredist installer warnings
    try rm -f "$WINDIR"/system32/comcat.dll
    try rm -f "$WINDIR"/system32/msvcrt.dll
    try rm -f "$WINDIR"/system32/oleaut32.dll
    try rm -f "$WINDIR"/system32/olepro32.dll
    try rm -f "$WINDIR"/system32/stdole2.tlb
    # vcredist still exits with status 43.  Anyone know why?
    $WINE "$WINETRICKS_CACHE"/vcredistsp6.exe

    status=$?
    case $status in
    0|43) ;;
    *) die $PACKAGE installation failed
    esac

    # And then some apps need mfc42u.dll, dunno what right way
    # is to get it, vcredist doesn't install it by default?
    try cabextract "$WINETRICKS_CACHE"/vcredistsp6.exe -d "$WINDIR"/system32/ -F mfc42u.dll

    override_dlls native,builtin msvcrt
}

#----------------------------------------------------------------

load_vcrun2003() {
    # Load the Visual C++ 2003 runtime libraries
    # Sadly, I know of no Microsoft URL for these
    echo "Installing BZFlag (which comes with the Visual C++ 2003 runtimes)"
    download . $SOURCEFORGE/bzflag/BZEditW32_1.6.5_Installer.exe bdd1b32c4202fd77e6513fd507c8236888b09121
    try $WINE "$WINETRICKS_CACHE"/BZEditW32_1.6.5_Installer.exe $WINETRICKS_S
    try cp "$programfilesdir_unix/BZEdit1.6.5"/m*71* "$WINDIR"/system32/
}

#----------------------------------------------------------------

load_vcrun2005() {
    # Load the latest Visual C++ 2005 runtime libraries
    # If this is just a dependency check, don't re-install

    # SP1 + ATL security fix build 4053 (MS09-035)
    # See http://www.microsoft.com/downloads/details.aspx?familyid=766A6AF7-EC73-40FF-B072-9112BAB119C2
    if test $PACKAGE != vcrun2005 && test -d "$WINDIR"/winsxs/x86_Microsoft.VC80.MFC_1fc8b3b9a1e18e3b_8.0.50727.4053_x-ww_b77cec8e
    then
        echo "prerequisite vcrun2005 already installed, skipping"
        return
    fi
    download vcrun2005-ms09-035 http://download.microsoft.com/download/6/B/B/6BB661D6-A8AE-4819-B79F-236472F6070C/vcredist_x86.exe e052789ebad7dc8d6f8505a9295b0576babd125e
    try $WINE "$WINETRICKS_CACHE"/vcrun2005-ms09-035/vcredist_x86.exe $WINETRICKS_QUIET
}

#----------------------------------------------------------------

load_vcrun2008() {
    # For the moment, assume windows already has this.
    case "$OS" in
    "Windows_NT")
      return
      ;;
    esac

    # Work around bug 5351 (http://bugs.winehq.org/show_bug.cgi?id=5351). In recent
    # wine (>= 1.1.7) the bug is fixed. The workaround doesn't hurt anything, and lets
    # installer work on older Wine (e.g., the stabe 1.0.1).
    volnum

    # Load the latest Visual C++ 2008 runtime libraries
    # If this is just a dependency check, don't re-install
    # SP1 + ATL security fix build 4148 (MS09-035)
    # See http://www.microsoft.com/downloads/details.aspx?familyid=2051a0c1-c9b5-4b0a-a8f5-770a549fd78c
    if test $PACKAGE != vcrun2008 && test -d "$WINDIR"/winsxs/x86_Microsoft.VC90.MFC_1fc8b3b9a1e18e3b_9.0.30729.4148_x-ww_a57c1f53
    then
        echo "prerequisite vcrun2008 already installed, skipping"
        return
    fi
    download vcrun2008-ms09-035 http://download.microsoft.com/download/9/7/7/977B481A-7BA6-4E30-AC40-ED51EB2028F2/vcredist_x86.exe bd18409cfe75b88c2a9432d36d96f4bf125a3237
    try $WINE "$WINETRICKS_CACHE"/vcrun2008-ms09-035/vcredist_x86.exe $WINETRICKS_QUIET

}

#----------------------------------------------------------------

load_vjrun20() {
    load_dotnet20
    # See http://www.microsoft.com/downloads/details.aspx?FamilyId=E9D87F37-2ADC-4C32-95B3-B5E3A21BAB2C
    download vjrun20 http://download.microsoft.com/download/9/2/3/92338cd0-759f-4815-8981-24b437be74ef/vjredist.exe 80a098e36b90d159da915aebfbfbacf35f302bd8
    if [ $WINETRICKS_QUIET ]
    then
        try $WINE "$WINETRICKS_CACHE"/vjrun20/vjredist.exe /q /C:"install /QNT"
    else
        try $WINE vjrun20/vjredist.exe
    fi
}

#----------------------------------------------------------------

load_vc2003toolkit()
{
    # http://www.dotnetmonster.com/Uwe/Forum.aspx/dotnet-vc/2679/ANN-Microsoft-Visual-C-Toolkit-2003
    #download .  http://download.microsoft.com/download/3/9/b/39bac755-0a1e-4d0b-b72c-3a158b7444c4/VCToolkitSetup.exe 956c81c3106b97042c4126b23c81885c4b5211f4
    # I'm going to link to this for now even though it's not straight from microsoft
    # because it's a developer tool rather than a runtime, and
    # I'm in the middle of trying to get lots of open source windows
    # apps to build on Wine; see http://wiki.winehq.org/UnitTestSuites
    # (Plus the sha1sum, which I still have from a copy straight from microsoft, 
    #  protects us somewhat against corrupted copies.)
    download . http://npg.dl.ac.uk/MIDAS/MIDAS_Release/VCToolkitSetup.exe 956c81c3106b97042c4126b23c81885c4b5211f4
    cd "$WINETRICKS_CACHE"
    try $WINE VCToolkitSetup.exe
    cd "$olddir"
}

#----------------------------------------------------------------

load_vc2005express()
{
    # Thanks to http://blogs.msdn.com/astebner/articles/551674.aspx for the recipe
    load_dotnet20
    load_msxml6

    # http://go.microsoft.com/fwlink/?LinkId=51410
    download vc2005express http://download.microsoft.com/download/8/3/a/83aad8f9-38ba-4503-b3cd-ba28c360c27b/ENU/vcsetup.exe 0292ae1d576edd8ee5350a27113c94c9f9958d5c

    if [ $WINETRICKS_QUIET ]
    then
        # Thanks to Aaron Stebner's unattended install recipe
        # http://blogs.msdn.com/astebner/archive/2006/03/19/555326.aspx

        # "http://go.microsoft.com/fwlink/?LinkId=51417"
        download vc2005express http://download.microsoft.com/download/0/5/A/05AA45B9-A4BE-4872-8D57-733DF5297284/Ixpvc.exe ce0da62b5649f33c7a150de276d799fb2d68d12a

        cd "$WINETRICKS_TMP"
        rm -rf vc2005express.tmp || true
        mkdir vc2005express.tmp
        cd vc2005express.tmp
        cabextract "$WINETRICKS_CACHE"/vc2005express/vcsetup.exe
        cp "$WINETRICKS_CACHE"/vc2005express/Ixpvc.exe .
        chmod +x Ixpvc.exe
        # Add /qn after ReallySuppress for a really silent install (but then you won't see any errors)

        try $WINE Ixpvc /t:"$WINETRICKS_TMP_WIN\\\\vc2005express.tmp" /q:a /c:"msiexec /i vcsetup.msi VSEXTUI=1 ADDLOCAL=ALL REBOOT=ReallySuppress"

        cd ..
        rm -rf vc2005express.tmp || true
    else
        warn "Don't forget to install the IDE, or mt.exe won't be installed"
        cd "$WINETRICKS_CACHE"/vc2005express
        try $WINE vcsetup.exe
        # The interactive installer seems to be asynchronous, so wait until its log file directory is created
        while test ! -d "$programfilesdir_unix/Microsoft Visual Studio 8/Microsoft Visual C++ 2005 Express Edition - ENU/Logs"
        do
          echo "Waiting for install to finish..."
          sleep 10
        done
    fi
    cd "$olddir"
}

#----------------------------------------------------------------

load_vc2005expresssp1()
{
    warn "$PACKAGE does not work yet"
    if test ! -f "$programfilesdir_unix/Microsoft Visual Studio 8/Common7/Tools/vsvars32.bat"
    then
        die "install vc2005express first (this verb will be merged into that once it's debugged)"
    fi

    # http://www.microsoft.com/downloads/details.aspx?FamilyId=7B0B0339-613A-46E6-AB4D-080D4D4A8C4E
    download vc2005express download.microsoft.com/download/7/7/3/7737290f-98e8-45bf-9075-85cc6ae34bf1/VS80sp1-KB926748-X86-INTL.exe 8b9a0172efad64774aa122f29e093ad2043b308d
    try $WINE "$WINETRICKS_CACHE"/vc2005express/VS80sp1-KB926748-X86-INTL.exe
}

load_vcdmount()
{
    if test "$WINE" != ""
    then
        return
    fi

    # Call only on real Windows.
    # Sets VCDMOUNT and ISO_MOUNT_ROOT

    # The only free mount tool I know for Windows Vista is Virtual CloneDrive,
    # which can be downloaded at 
    # http://www.slysoft.com/en/virtual-clonedrive.html
    # FIXME: actually install it here

    # Locate vcdmount.exe.   FIXME: internationalize this.
    for PROGRAMFILES in "c:/Program Files (x86)" "c:/Program Files"
    do
        test -d "$PROGRAMFILES" && break
    done
    VCDMOUNT="$PROGRAMFILES/Elaborate Bytes/VirtualCloneDrive/vcdmount.exe"
    if test ! -x "$VCDMOUNT" 
    then
        warn "Installing Virtual CloneDrive"
        download . http://static.slysoft.com/SetupVirtualCloneDrive.exe
        # have to use cmd else vista won't let cygwin run .exe's?
        chmod +x "$WINETRICKS_CACHE"/SetupVirtualCloneDrive.exe
        cmd /c "$WINETRICKS_CACHE_WIN"\\SetupVirtualCloneDrive.exe
    fi

    # FIXME: Use WMI to locate the drive named
    # "ELBY CLONEDRIVE..." using WMI as described in
    # http://delphihaven.wordpress.com/2009/07/05/using-wmi-to-get-a-drive-friendly-name/
    # For now, you just have to hardcode it for your system :-(
    warn "You probably need to edit the script to tell it which drive VirtualCloneDrive picked"
    for letter in e f g h
    do
        ISO_MOUNT_ROOT=/cygdrive/$letter
        test -d $ISO_MOUNT_ROOT || break
    done
    test -d $ISO_MOUNT_ROOT && die "cannot find the VirtualCloneDrive"
}

iso_mount()
{
    my_img="$1"

    if test "$WINE" = ""
    then
        load_vcdmount
        my_img_win="`$XXXPATH -w $my_img`"
        try "$VCDMOUNT" /l=$letter "$my_img_win"
        while ! test -d "$ISO_MOUNT_ROOT"
        do
            echo "Waiting for mount to finish"
            sleep 1
        done
    else 
        # Linux
        case "$GUI-$DE" in
        gnome) 
          1-gksudo "mkdir -p $ISO_MOUNT_ROOT"
          1-gksudo "mount -o ro,loop $my_img $ISO_MOUNT_ROOT"
          ;;
        *)
          sudo mkdir -p $ISO_MOUNT_ROOT
          sudo mount -o ro,loop "$my_img" $ISO_MOUNT_ROOT
          ;;
        esac
    fi
}

iso_umount()
{
    if test "$WINE" = ""
    then
        # Windows
        load_vcdmount
        "$VCDMOUNT" /u
    else
        case "$GUI-$DE" in
        gnome) 
          1-gksudo "umount $ISO_MOUNT_ROOT"
          1-gksudo "rm -rf $ISO_MOUNT_ROOT"
          ;;
        *)
          sudo umount $ISO_MOUNT_ROOT
          sudo rm -rf $ISO_MOUNT_ROOT
          ;;
        esac
    fi
}

#----------------------------------------------------------------

load_vc2005trial()
{
    load_vcrun6
    load_dotnet20
    load_msxml6
    load_vcdmount

    warn "Downloading/checksumming Visual C++ 2005 Trial.  This will take some time!"
    download vc2005trial http://download.microsoft.com/download/6/f/5/6f5f7a01-50bb-422d-8742-c099c8896969/En_vs_2005_vsts_180_Trial.img f66ae07618d67e693ca0524d3582208c20e07823

    iso_umount
    iso_mount "$WINETRICKS_CACHE"/vc2005trial/En_vs_2005_vsts_180_Trial.img

    # FIXME: do this right
    rm -f /cygdrive/c/Users/$USER/AppData/local/temp/dd_vsinstall80.txt

    if false && [ $WINETRICKS_QUIET ]
    then
      # Thanks to http://blogs.msdn.com/astebner/archive/2005/05/24/421314.aspx for unattended install method
      # See also http://support.microsoft.com/?kbid=913445
      die "not implemented yet"
    else
      cd $ISO_MOUNT_ROOT/vs/Setup
      try $WINE setup.exe

      # FIXME: unify these and make the windows one right
      if test "$WINE" = ""
      then
          while ! tr -d '\000' < /cygdrive/c/Users/$USER/AppData/local/temp/dd_vsinstall80.txt | grep End_Session
          do
              sleep 10
              echo waiting for setup to finish
          done
      else
          while ps $psargs | grep -q setup.exe
          do
              sleep 10
              echo waiting for setup to finish
          done
      fi
      cd "$olddir"
    fi

    iso_umount
}

#----------------------------------------------------------------

load_vc2005sp1()
{
    if test ! -f "$programfilesdir_unix/Microsoft Visual Studio 8/Common7/Tools/vsvars32.bat"
    then
        die "install vc2005trial first (this verb will be merged into that once it's debugged)"
    fi

    # http://www.microsoft.com/downloads/details.aspx?FamilyID=bb4a75ab-e2d4-4c96-b39d-37baf6b5b1dc
    download vc2005sp1 http://download.microsoft.com/download/6/3/c/63c69e5d-74c9-48ea-b905-30ac3831f288/VS80sp1-KB926601-X86-ENU.exe d4b5c73253a7a4f5b4b389f41b94fea4a7247b57
    cd "$WINETRICKS_CACHE"/vc2005sp1
    try $WINE VS80sp1-KB926601-X86-ENU.exe
    cd "$olddir"
}

#----------------------------------------------------------------

load_vc2008trial()
{
    load_vcrun6
    load_dotnet20
    load_msxml6
    load_vcdmount

    warn "Visual C++ 2008 Trial does not yet work in Wine, and this recipe isn't quite debugged even in windows yet."
    warn "Downloading/checksumming Visual C++ 2008 Trial.  This will take some time!"
    # http://www.microsoft.com/downloads/details.aspx?FamilyID=83c3a1ec-ed72-4a79-8961-25635db0192b
    download vc2008trial http://download.microsoft.com/download/8/1/d/81d3f35e-fa03-485b-953b-ff952e402520/VS2008ProEdition90dayTrialENUX1435622.iso dfb601096f62fd75af6ad62b277be79793f53b56

    iso_umount
    iso_mount "$WINETRICKS_CACHE"/vc2008trial/VS2008ProEdition90dayTrialENUX1435622.iso

    # FIXME: do this right
    rm -f /cygdrive/c/Users/$USER/AppData/local/temp/dd_vsinstall80.txt

    if true
    then
      cd $ISO_MOUNT_ROOT/Setup
      try $WINE setup.exe

      # FIXME: unify these and make the windows one right
      if test "$WINE" = ""
      then
          while ! tr -d '\000' < /cygdrive/c/Users/$USER/AppData/local/temp/dd_vsinstall80.txt | grep End_Session
          do
              sleep 10
              echo waiting for setup to finish
          done
      else
          while ps $psargs | grep -q setup.exe
          do
              sleep 10
              echo waiting for setup to finish
          done
      fi
      cd "$olddir"
    fi

    iso_umount
}

#----------------------------------------------------------------

load_vc2008sp1()
{
    echo pfdu is ${programfilesdir_unix}
    echo setting FOO
    FOO="${programfilesdir_unix}/Microsoft Visual Studio 9.0/Microsoft Visual Studio 2008 Professional Edition - ENU"
    echo FOO set to $FOO
    if test ! -d "$FOO"
    then
        die "install vc2008trial first (this verb will be merged into that once it's debugged)"
    fi

    # http://www.microsoft.com/downloads/details.aspx?familyid=FBEE1648-7106-44A7-9649-6D9F6D58056E
    download vc2008sp1 http://download.microsoft.com/download/f/6/7/f67fdf05-0586-413f-8231-affbe12f80a8/VS90sp1-KB945140-ENU.exe 91c146dffa96c6d8f9ceb2b5235b04349cf93ed9
    cd "$WINETRICKS_CACHE"/vc2008sp1
    try $WINE VS90sp1-KB945140-ENU.exe
    cd "$olddir"
}

#----------------------------------------------------------------

load_vlc() {
    download . http://www.videolan.org/mirror-geo-redirect.php?file=vlc/0.8.6f/win32/vlc-0.8.6f-win32.exe b83558e4232c47a385dbc93ebdc2e6b942fbcfbf
    try $WINE "$WINETRICKS_CACHE"/vlc-0.8.6f-win32.exe $WINETRICKS_S
}

#----------------------------------------------------------------

load_wininet() {
    # This is an updated wininet from IE 5.0.1.
    # (Good enough for Active Worlds browser.  Also helps "Avatar - Legends of the Arena" get to login screen.)
    # See http://www.microsoft.com/downloads/details.aspx?familyid=6DEE32AB-B618-4FB3-9A45-CDD08162E167
    download . http://download.microsoft.com/download/ie5/Update/1/WIN98/EN-US/3725.exe b048e0b4e303298de3317b16f7008c43ca71ddfe
    try cabextract --directory="$WINETRICKS_TMP" "$WINETRICKS_CACHE/3725.exe"
    try cp -f "$WINETRICKS_TMP"/Wininet.dll "$WINDIR"/system32/wininet.dll
    override_dlls native,builtin wininet
}

#----------------------------------------------------------------

load_wme9() {
    # See also http://www.microsoft.com/downloads/details.aspx?FamilyID=5691ba02-e496-465a-bba9-b2f1182cdf24
    download wme9 http://download.microsoft.com/download/8/1/f/81f9402f-efdd-439d-b2a4-089563199d47/WMEncoder.exe 7a3f8781f3e5705651992ef0150ee30bc1295116

    try $WINE "$WINETRICKS_CACHE"/wme9/WMEncoder.exe $WINETRICKS_QUIET
}

#----------------------------------------------------------------

load_wmp9() {
    # Not really expected to work well yet; see
    # http://appdb.winehq.org/appview.php?versionId=1449

    load_wsh56

    set_winver win2k

    # See also http://www.microsoft.com/windows/windowsmedia/player/9series/default.aspx
    download wmp9 http://download.microsoft.com/download/1/b/c/1bc0b1a3-c839-4b36-8f3c-19847ba09299/MPSetup.exe 580536d10657fa3868de2869a3902d31a0de791b

    # Have to run twice; see http://bugs.winehq.org/show_bug.cgi?id=1886
    try $WINE "$WINETRICKS_CACHE"/wmp9/MPSetup.exe $WINETRICKS_QUIET
    try $WINE "$WINETRICKS_CACHE"/wmp9/MPSetup.exe $WINETRICKS_QUIET

    # Also install the codecs
    # See http://www.microsoft.com/downloads/details.aspx?FamilyID=06fcaab7-dcc9-466b-b0c4-04db144bb601
    download . http://download.microsoft.com/download/5/c/2/5c29d825-61eb-4b16-8eb8-58367d0464d5/WM9Codecs9x.exe 8b76bdcbea0057eb12b7966edab4b942ddacc253
    try $WINE "$WINETRICKS_CACHE"/WM9Codecs9x.exe $WINETRICKS_QUIET

    # Disable WMP's services, since they depend on unimplemented stuff, they trigger the GUI debugger several times
    try_regedit /D "HKEY_LOCAL_MACHINE\System\CurrentControlSet\Services\Cdr4_2K"
    try_regedit /D "HKEY_LOCAL_MACHINE\System\CurrentControlSet\Services\Cdralw2k"

    unset_winver
}

#----------------------------------------------------------------

load_wmp10() {
    # See http://appdb.winehq.org/appview.php?iVersionId=3212

    load_wsh56

    # See also http://www.microsoft.com/windows/windowsmedia/player/10
    download . http://download.microsoft.com/download/1/2/A/12A31F29-2FA9-4F50-B95D-E45EF7013F87/MP10Setup.exe 69862273a5d9d97b4a2e5a3bd93898d259e86657

    # Crashes on exit, but otherwise ok; see http://bugs.winehq.org/show_bug.cgi?id=12633
    echo Executing $WINE "$WINETRICKS_CACHE"/MP10Setup.exe $WINETRICKS_QUIET
    try $WINE "$WINETRICKS_CACHE"/MP10Setup.exe $WINETRICKS_QUIET

    # Also install the codecs
    # See http://www.microsoft.com/downloads/details.aspx?FamilyID=06fcaab7-dcc9-466b-b0c4-04db144bb601
    download . http://download.microsoft.com/download/5/c/2/5c29d825-61eb-4b16-8eb8-58367d0464d5/WM9Codecs9x.exe 8b76bdcbea0057eb12b7966edab4b942ddacc253
    set_winver win2k
    try $WINE "$WINETRICKS_CACHE"/WM9Codecs9x.exe $WINETRICKS_QUIET

    # Disable WMP's services, since they depend on unimplemented stuff, they trigger the GUI debugger several times
    try_regedit /D "HKEY_LOCAL_MACHINE\System\CurrentControlSet\Services\Cdr4_2K"
    try_regedit /D "HKEY_LOCAL_MACHINE\System\CurrentControlSet\Services\Cdralw2k"

    unset_winver
}

#----------------------------------------------------------------

load_wenquanyi() {
    # See http://wenq.org/enindex.cgi
    # Donate at http://wenq.org/enindex.cgi?Download(en)#MicroHei_Beta if you want to help support free CJK font development
    download . $SOURCEFORGE/wqy/wqy-microhei-0.2.0-beta.tar.gz 28023041b22b6368bcfae076de68109b81e77976
    cd "$WINETRICKS_TMP/"
    gunzip -dc "$WINETRICKS_CACHE/wqy-microhei-0.2.0-beta.tar.gz" | tar -xf -
    try mv wqy-microhei/wqy-microhei.ttc "$winefontsdir"
    register_font wqy-microhei.ttc "WenQuanYi Micro Hei"
    cd "$olddir"
}

#----------------------------------------------------------------

load_wsh56() {
    # If this is just a dependency check, don't re-install
    if test $PACKAGE != wsh56 && test -f "$WINDIR"/system32/wscript.exe
    then
        echo "prerequisite wsh56 already installed, skipping"
        return
    fi

    load_vcrun6

    # See also http://www.microsoft.com/downloads/details.aspx?displaylang=en&FamilyID=C717D943-7E4B-4622-86EB-95A22B832CAA
    download . http://download.microsoft.com/download/2/8/a/28a5a346-1be1-4049-b554-3bc5f3174353/WindowsXP-Windows2000-Script56-KB917344-x86-enu.exe f4692766caa3ee9b38d4166845486c6199a33457

    try $WINE "$WINETRICKS_CACHE"/WindowsXP-Windows2000-Script56-KB917344-x86-enu.exe $WINETRICKS_QUIET
}

#----------------------------------------------------------------

load_wsh56js() {
    # This installs jscript 5.6 (but not vbscript)
    # See also http://www.microsoft.com/downloads/details.aspx?FamilyID=16dd21a1-c4ee-4eca-8b80-7bd1dfefb4f8&DisplayLang=en
    download . http://download.microsoft.com/download/b/c/3/bc3a0c36-fada-497d-a3de-8b0139766f3b/Windows2000-KB917344-56-x86-enu.exe add5f74c5bd4da6cfae47f8306de213ec6ed52c8

    try $WINE "$WINETRICKS_CACHE"/Windows2000-KB917344-56-x86-enu.exe $WINETRICKS_QUIET
}

#----------------------------------------------------------------

load_wsh56vb() {
    # This installs vbscript 5.6 (but not jscript)
    # See also http://www.microsoft.com/downloads/details.aspx?familyid=4F728263-83A3-464B-BCC0-54E63714BC75
    download . http://download.microsoft.com/download/IE60/Patch/Q318089/W9XNT4Me/EN-US/vbs56men.exe 48f14a93db33caff271da0c93f334971f9d7cb22

    try $WINE "$WINETRICKS_CACHE"/vbs56men.exe $WINETRICKS_QUIET
}

#----------------------------------------------------------------

load_xact() {
    helper_directx_dl ;

    # xactengine?_?.dll, X3DAudio?_?.dll, xaudio?_?.dll, xapofx?_?.dll - extract
    cabextract -d "$WINETRICKS_TMP" -L -F '*_xact_*x86*' "$WINETRICKS_CACHE"/$DIRECTX_NAME
    cabextract -d "$WINETRICKS_TMP" -L -F '*_x3daudio_*x86*' "$WINETRICKS_CACHE"/$DIRECTX_NAME
    cabextract -d "$WINETRICKS_TMP" -L -F '*_xaudio_*x86*' "$WINETRICKS_CACHE"/$DIRECTX_NAME
    for x in `ls "$WINETRICKS_TMP"/*.cab`
    do
      cabextract -d "$WINDIR"/system32 -L -F '*.dll' "$x"
    done

    # xactengine?_?.dll, xaudio?_?.dll - register
    for x in `ls "$WINDIR"/system32/xactengine* "$WINDIR"/system32/xaudio*`
    do
      try $WINE regsvr32 `basename $x`
    done
}

#----------------------------------------------------------------

load_xvid() {
    # xvid
    load_vcrun6
    download . http://www.koepi.info/Xvid-1.2.2-07062009.exe 435203e7f713c4484ca4f50f43e847f3dc118962
    try $WINE "$WINETRICKS_CACHE"/Xvid-1.2.2-07062009.exe $WINETRICKS_SILENT
}

#----------------------------------------------------------------

print_version() {
    echo "$VERSION"
}

#--------- Main program -----------------------------------------

# On Solaris, choose more modern commands (needed for id -u).
case `uname -s` in
SunOS) PATH="/usr/xpg6/bin:/usr/xpg4/bin:$PATH"
      ;;
esac

case "$1" in
-V|--version)
    echo "Winetricks version $VERSION.  (C) 2007-2009 Dan Kegel et al.  LGPL."
    exit 0
    ;;
esac

detectDE

GUI=0
case x"$1" in
x) GUI=1 ;;
x-h|x--help|xhelp) usage ; exit 1 ;;
esac

case "$OS" in
 "Windows_NT")
  ;;
 *)
  # Prevent running with wrong user id.
  # It's bad to create files as the wrong user!
  die_if_user_not_dirowner "$WINEPREFIX"
  die_if_user_not_dirowner "$WINETRICKS_CACHE"

  if [ ! -x "`which "$WINE"`" ]
  then
      die "Cannot find wine ($WINE)"
  fi

  # Create wineprefix if not already there
  test -d "$WINEPREFIX" || WINEDLLOVERRIDES=mshtml= $WINE cmd /c echo yes > /dev/null 2>&1
  ;;
esac

mkdir -p "$WINETRICKS_TMP"

case $GUI in
1) dogui ; set $todo ;;
esac

mkdir -p "$WINETRICKS_CACHE"
olddir=`pwd`
# Clean up after failed runs, if needed
rm -rf "$WINETRICKS_TMP"/*

# The folder-name is localized!
programfilesdir_win="`unset WINEDEBUG; WINEDLLOVERRIDES=mshtml= $WINE cmd.exe /c echo "%ProgramFiles%"`"
test x"$programfilesdir_win" != x || die "$WINE cmd.exe /c echo '%ProgramFiles%' returned empty string"
programfilesdir_unix="`unset WINEDEBUG; $XXXPATH -u "$programfilesdir_win" | tr -d '\015' `"
test x"$programfilesdir_unix" != x || die "winepath -u $programfilesdir_win returned empty string"
winetricks_tmp_win="`$XXXPATH -w "$WINETRICKS_TMP"`"

# (Fixme: get fonts path from SHGetFolderPath
# See also http://blogs.msdn.com/oldnewthing/archive/2003/11/03/55532.aspx)
#
# Did the user rename Fonts to fonts?
if test ! -d "$WINDIR"/Fonts && test -d "$WINDIR"/fonts
then
    winefontsdir="$WINDIR"/fonts
else
    winefontsdir="$WINDIR"/Fonts
fi

# Mac folks tend to not have sha1sum, but we can make do with openssl
if [ -x "`which sha1sum`" ]
then
   SHA1SUM="sha1sum"
elif [ -x "`which openssl`" ]
then
   SHA1SUM="openssl dgst -sha1"
else
   die "No sha1sum utility available."
fi

if [ ! -x "`which cabextract`" ]
then
    die "Cannot find cabextract.  Please install it (e.g. 'sudo apt-get install cabextract' or 'sudo yum install cabextract')."
fi

if [ ! -x "`which unzip`" ]
then
    die "Cannot find unzip.  Please install it (e.g. 'sudo apt-get install unzip' or 'sudo yum install unzip')."
fi

while test "$1" != ""
do
    PACKAGE=$1
    case $1 in
    -q) WINETRICKS_QUIET="/q"
        WINETRICKS_QUIET_T="/qt" # Microsoft Control Pad
        WINETRICKS_UNIXQUIET="-q"
        WINETRICKS_SILENT="/silent"
        WINETRICKS_S="/S"                 # for NSIS installers
        WINEDEBUG=${WINEDEBUG:-"fixme-all"}
        export WINEDEBUG
        ;;
    -v) set -x;;
    art2kmin|art2k7min) load_art2kmin;;
    atmlib) load_atmlib;;
    autohotkey|ahk) load_autohotkey;;
    cmake) load_cmake;;
    comctl32.ocx) load_comctl32ocx;;
    comctl32|cc580) load_cc580;;
    colorprofile) load_colorprofile;;
    controlpad|fm20) load_controlpad;;
    corefonts) load_corefonts;;
    cygwin) load_cygwin;;
    d3dx9) load_d3dx9;;
    dcom98) load_dcom98;;
    dinput8) load_dinput8;;
    dirac|dirac0.8) load_dirac08;;
    directplay|dxplay|dplay) load_directplay;;
    directx9) load_directx9;;
    divx) load_divx;;
    dotnet1|dotnet11) load_dotnet11; load_fontfix;;
    dotnet11sdk) load_dotnet11sdk;;
    dotnet2|dotnet20) load_dotnet20; load_fontfix;;
    dotnet20sdk) load_dotnet20sdk;;
    dotnet20sp2) load_dotnet20sp2; load_fontfix;;
    dotnet3|dotnet30) load_dotnet30; load_fontfix;;
    dotnet35) load_dotnet35; load_fontfix;;
    droid) load_droid;;
    ffdshow) load_ffdshow;;
    firefox|firefox3) load_firefox;;
    flash) load_flash;;
    fontfix) load_fontfix;;
    fontsmooth-bgr|fs-bgr) load_fs_bgr;;
    fontsmooth-disable|fs-disable) load_fs_disable;;
    fontsmooth-gray|fontsmooth-grayscale|fontsmooth-enable|fs-enable) load_fs_grayscale;;
    fontsmooth-rgb|fs-rgb|fs-cleartype) load_fs_rgb;;
    gdiplus) load_gdiplus;;
    gecko) load_gecko;;
    gecko-dbg|geckodbg|gecko_dbg|geckodebug|gecko_debug|gecko-debug) load_gecko_dbg;;
    glsl-disable) load_glsl_disable;;
    glsl-enable) load_glsl_enable;;
    hosts) load_hosts;;
    icodecs) load_icodecs;;
    ie6) load_ie6;;
    ie7) load_ie7;;
    jet40) load_jet40;;
    kde) load_kde;;
    liberation) load_liberation;;
    mdac25) load_mdac25;;
    mdac27) load_mdac27;;
    mdac28) load_mdac28;;
    mfc40) load_mfc40;;
    mingw|mingw-min|mingw_min) load_mingw_min;;
    mingw-gdb|mingw_gdb) load_mingw_gdb;;
    mono19|mono20) load_mono20;;
    mono22) load_mono22;;
    mono24) load_mono24;;
    mozillabuild) load_mozillabuild;;
    mpc) load_mpc;;
    msi2) load_msi2;;
    mshflxgd) load_mshflxgd;;
    msls31) load_msls31;;
    msmask) load_msmask;;
    msscript) load_msscript;;
    msxml3) load_msxml3;;
    msxml4) load_msxml4;;
    msxml6) load_msxml6;;
    ogg) load_ogg;;
    ole2) load_ole2;;
    openwatcom|watcom) load_openwatcom;;
    pdh) load_pdh;;
    physx) load_physx;;
    psdk2003) load_psdk2003;;
    psdkvista) load_psdkvista;;
    psdkwin7) load_psdkwin7;;
    python|python26) load_python26;;
    python-comtypes|pythoncom|python-com|pythoncomtypes) load_python_comtypes;;
    quicktime72) load_quicktime72;;
    riched20) load_riched20;;
    riched30) load_riched30;;
    richtx32) load_richtx32;;
    shockwave) load_shockwave;;
    tahoma) load_tahoma;;
    urlmon) load_urlmon;;
    vbrun200|vb2run) load_vb2run;;
    vbrun300|vb3run) load_vb3run;;
    vbrun400|vb4run) load_vb4run;;
    vbrun500|vbvm50|vb5run) load_vbvm50;;
    vbrun600|vbrun60|vb6run) load_vbrun60;;
    vc2003toolkit) load_vc2003toolkit;;
    vc2005express) load_vc2005express;;
    vc2005expresssp1) load_vc2005expresssp1;;
    vc2005sp1) load_vc2005sp1;;
    vc2008sp1) load_vc2008sp1;;
    vc2005trial) load_vc2005trial;;
    vc2008trial) load_vc2008trial;;
    vcrun600|vcrun60|vcrun6|mfc42) load_vcrun6;;
    vcrun60sp6|vcrun6sp6) load_vcrun6sp6;;
    vcrun2003) load_vcrun2003;;
    vcrun2005|vcrun2005sp1) load_vcrun2005;;
    vcrun2008|vcrun2008sp1) load_vcrun2008;;
    vjrun20) load_vjrun20;;
    vlc) load_vlc;;
    wenquanyi) load_wenquanyi;;
    wininet) load_wininet;;
    wme9) load_wme9;;
    wmp9) load_wmp9;;
    wmp10) load_wmp10;;
    wsh56) load_wsh56;;
    wsh56js) load_wsh56js;;
    wsh56vb) load_wsh56vb;;
    xact|xactengine|x3daudio) load_xact;;
    xvid) load_xvid;;

    allcodecs|allvcodecs) load_vcrun6; load_ffdshow; load_xvid; load_icodecs;;
    allfonts) load_corefonts; load_tahoma; load_liberation; load_droid; load_wenquanyi;;
    fakeie6) set_fakeie6;;
    heapcheck) set_heapcheck;;
    native_mdac) set_native_mdac;;
    native_oleaut32) override_dlls native,builtin oleaut32;;
    nocrashdialog) disable_crashdialog;;
    orm=backbuffer|backbuffer) set_orm backbuffer;;
    orm=fbo|fbo) set_orm fbo;;
    orm=pbuffer|pbuffer) set_orm pbuffer;;
    sandbox) sandbox;;
    sound=alsa|alsa) set_sound_driver alsa;;
    sound=audioio|audioio) set_sound_driver audioio;;
    sound=coreaudio|coreaudio) set_sound_driver coreaudio;;
    sound=esound|esound) set_sound_driver esound;;
    sound=jack|jack) set_sound_driver jack;;
    sound=nas|nas) set_sound_driver nas;;
    sound=oss|oss) set_sound_driver oss;;
    version) print_version;;
    volnum) volnum;;
    winver=nt40|nt40) set_winver nt40;;
    winver=win98|win98) set_winver win98;;
    winver=win2k|win2k) set_winver win2k;;
    winver=winxp|winxp) set_winver winxp;;
    winver=vista|vista) set_winver vista;;
    winver=) unset_winver;;
    *) echo Unknown arg $1; usage ; exit 1;;
    esac
    # Provide a bit of feedback
    test "$WINETRICKS_QUIET" = "" && case $1 in
    -q) echo Setting quiet mode;;
    -v) echo Setting verbose mode;;
    *) echo "Install of $1 done" ;;
    esac
    shift
    # cleanup
    rm -rf "$WINETRICKS_TMP"/*
done

    # remove the temp directory
    rm -rf "$WINETRICKS_TMP"

test "$WINETRICKS_QUIET" = "" && echo winetricks done. || true
