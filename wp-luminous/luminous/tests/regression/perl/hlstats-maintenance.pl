#!/usr/bin/perl
# HLstatsX Community Edition - Real-time player and clan rankings and statistics
# Copyleft (L) 2008-20XX Nicholas Hastings (nshastings@gmail.com)
# http://www.hlxce.com
#
# HLstatsX Community Edition is a continuation of 
# ELstatsNEO - Real-time player and clan rankings and statistics
# Copyleft (L) 2008-20XX Malte Bayer (steam@neo-soft.org)
# http://ovrsized.neo-soft.org/
# 
# ELstatsNEO is an very improved & enhanced - so called Ultra-Humongus Edition of HLstatsX
# HLstatsX - Real-time player and clan rankings and statistics for Half-Life 2
# http://www.hlstatsx.com/
# Copyright (C) 2005-2007 Tobias Oetzel (Tobi@hlstatsx.com)
#
# HLstatsX is an enhanced version of HLstats made by Simon Garner
# HLstats - Real-time player and clan rankings and statistics for Half-Life
# http://sourceforge.net/projects/hlstats/
# Copyright (C) 2001  Simon Garner
#             
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
# 
# For support and installation notes visit http://www.hlxce.com

use strict;
use warnings;

##
## Settings
##

# $opt_configfile - Absolute path and filename of configuration file.
my $opt_configfile = "./hlstats.conf";

# $opt_libdir - Directory to look in for local required files
#               (our *.plib, *.pm files).
our $opt_libdir = "./";


##
##
################################################################################
## No need to edit below this line
##


use Getopt::Long;
use DBI;
use Encode;
use Carp;

require ConfigReaderSimple;
require HLstats_DB;
require HLstats_Common;
HLstats_Common->import(qw/FormatNumber FormatDate ReadConfigFile PrintEvent Abbr IsNumber PrintNotice/);

$|=1;
Getopt::Long::Configure ("bundling");


binmode STDIN, ":utf8";
binmode STDOUT, ":utf8";

##
## MAIN
##

# Options

my $configfile = undef;
my $opt_help = 0;
my $opt_version = 0;
my $opt_numdays = 1;
my $opt_player_activity = 0;
my $opt_awards = 0;
my $opt_ribbons = 0;
my $opt_geoip = 0;
my $opt_clans = 0;
my $opt_prune = 0;
my $opt_optimize = 0;
my $opt_verbose = 0;
our $opt_cpanelhack = 0;

our $g_db = undef;
our $db_host = "localhost";
our $db_user = "";
our $db_pass = "";
our $db_name = "hlstats";

my $date_ubase="";
my $date_base="CURRENT_DATE()";


my $usage = <<EOT
Usage: hlstats-awards.pl [OPTION]...
Generate awards from Half-Life server statistics.
        
 Actions (specify none or more, default -iarp):
  -i, --inactive                  Calculate player activity
                                    and deactivate inactive players
  -a, --awards                    Process daily awards (see --date descr for more info)
  -r, --ribbons                   Process ribbons
  -g, --geoip                     Attempt to lookup and store locations for players with
                                    unknown/no location. (run after updating geoip data)
  -t, --clans                     Recalculate player's clan affiliations
  -p, --prune                     Prune old events and sessions
  -o, --optimize                  Optimize all db tables

 Other options:
  -h, --help                      display this help and exit
  -v, --version                   output version information and exit
      --numdays                   number of days in period for awards
      --date=YYYY-MM-DD           day after date to calculate awards for (defaults to today) 
                                    If you specify a date like 2008-01-04 it will do awards
                                    based on 2008-01-03 stats
      --db-host=HOST              database ip:port
      --db-name=DATABASE          database name
      --db-password=PASSWORD      database password (WARNING: specifying the
                                    password on the command line is insecure.
                                    Use the configuration file instead.)
      --db-username=USERNAME      database username
  -c, --configfile                Specific configfile to use, settings in this file can't
                                  be overided with commandline settings.

Long options can be abbreviated, where such abbreviation is not ambiguous.

Most options can be specified in the configuration file:
  $opt_configfile
Note: Options set on the command line take precedence over options set in the
configuration file.

HLstatsX:CE: http://www.hlxce.com
EOT
;

# Read Config File

my %conf_directives = (
        "DBHost",                       "db_host",
        "DBUsername",           "db_user",
        "DBPassword",           "db_pass",
        "DBName",                       "db_name",
        "CpanelHack",           "opt_cpanelhack"
);

ReadConfigFile($opt_configfile);

# Read Command Line Arguments

GetOptions(
        "help|h"                        => \$opt_help,
        "version|v"                     => \$opt_version,
        "numdays=i"                     => \$opt_numdays,
        "date=s"                        => \$date_ubase,
        "inactive|i"            => \$opt_player_activity,
        "awards|a"                      => \$opt_awards,
        "ribbons|r"                     => \$opt_ribbons,
        "geoip|g"                       => \$opt_geoip,
        "clans|t"                       => \$opt_clans,
        "prune|p"                       => \$opt_prune,
        "optimize|o"            => \$opt_optimize,
        "db-host=s"                     => \$db_host,
        "db-name=s"                     => \$db_name,
        "db-password=s"         => \$db_pass,
        "db-username=s"         => \$db_user,
        "configfile|c=s"        => \$configfile,
        "verbose"                       => \$opt_verbose
) or die($usage);

if ($opt_help)
{
        print $usage;
        exit(0);
}

if (defined($configfile))
{
        ReadConfigFile($configfile);
}

if ($opt_version)
{
        $g_db = HLstats_DB->new($db_host, $db_name, $db_user, $db_pass);
        my $result = $g_db->DoQuery("SELECT value FROM hlstats_Options WHERE keyname='version'");

        if ($result->rows > 0)
        {
                $HLstats_Common::version = $result->fetchrow_array;
        }
        $result->finish;
        
        my $v = $HLstats_Common::version;
        
        print <<"EOF"
hlstats-awards.pl (HLX:CE Awards Script) Version $v
Real-time player and clan rankings and statistics for Half-Life

Copyright (C) 2001  Simon Garner
Modified & Enhanced in 2005 by Tobias Oetzel (Tobi\@gameme.de)

This is free software; see the source for copying conditions.  There is NO
warranty; not even for MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

EOF
        ;
        exit(0);
}

print "-- Connecting to MySQL database '$db_name' on '$db_host' as user '$db_user' ... ";

$g_db = HLstats_DB->new($db_host, $db_name, $db_user, $db_pass);

print "connected OK\n";

if ($date_ubase)
{
        $date_base = "'" . $date_ubase . "'";
}

if (0 == ($opt_player_activity + $opt_awards + $opt_ribbons + $opt_geoip + $opt_clans + $opt_prune + $opt_optimize))
{
        $opt_player_activity = 1;
        $opt_awards = 1;
        $opt_ribbons = 1;
        $opt_prune = 1;
}

# Startup

print "++ HLstatsX:CE Awards & Maintenance script version ".$HLstats_Common::version." starting...\n\n";

DoInactive() if ($opt_player_activity);
DoAwards() if ($opt_awards);
DoRibbons() if ($opt_ribbons);
DoGeoIP() if ($opt_geoip);
DoClans() if ($opt_clans);
DoPruning() if ($opt_prune);
DoOptimize() if ($opt_optimize);

print "\n++ HLstatsX:CE Awards & Maintenance script finished.\n\n";

sub DoInactive
{
        print "++ Updating player activity... ";
        my $minactivity = 2419200;
        # Inactive Players
        my $result = $g_db->DoQuery("
                SELECT
                        value
                FROM
                        hlstats_Options
                WHERE
                        keyname = 'MinActivity'
        ");

        if ($result->rows > 0) {
                my ($tempminact) = $result->fetchrow_array;
                $minactivity = $tempminact * 86400;
        }
        
        if ($minactivity > 0)
        {
                my $timestamp = 0;
        
                $result = $g_db->DoQuery("
                        SELECT
                                value
                        FROM
                                hlstats_Options
                        WHERE
                                keyname = 'UseTimestamp'
                ");
                
                
                if ($result->rows > 0) {
                        ($timestamp) = $result->fetchrow_array;
                }
                
                my %last_events = ();
                
                if ($timestamp > 0)
                {
                        $result = $g_db->DoQuery("
                                SELECT
                                        game,
                                        MAX(last_event)
                                FROM
                                        hlstats_Servers
                                GROUP BY
                                        game
                        ");
                        my %last_events = ();
        
        
                        while ( my($game, $last) = $result->fetchrow_array) {
                                $last_events{$game} = $last
                        }
                        while ( my($game, $last) = each(%last_events))
                        {
                                $g_db->DoFastQuery("
                                        UPDATE
                                                hlstats_Players
                                        SET
                                                hlstats_Players.activity = IF(($minactivity > $last - hlstats_Players.last_event), ((100 / $minactivity) * ($minactivity - ($last - hlstats_Players.last_event))), -1)
                                        WHERE
                                                hlstats_Players.game = ".$g_db->Quote($game)."
                                ");
                        }
                }
                else
                {
                        $g_db->DoFastQuery("
                                UPDATE
                                        hlstats_Players
                                SET
                                        hlstats_Players.activity = IF(($minactivity > UNIX_TIMESTAMP() - hlstats_Players.last_event), ((100 / $minactivity) * ($minactivity - (UNIX_TIMESTAMP() - hlstats_Players.last_event))), -1)
                        ");
                }
        
                $g_db->DoFastQuery("
                        UPDATE
                                hlstats_Players
                        SET
                                hideranking = 3
                        WHERE
                                hideranking = 0
                                AND activity < 0
                ");
        }
        
        print "done\n";
}

sub DoAwards
{
        print "++ Processing awards... ";
        
        my $resultAwards = $g_db->DoQuery("
                SELECT
                        hlstats_Awards.awardId,
                        hlstats_Awards.game,
                        hlstats_Awards.awardType,
                        hlstats_Awards.code
                FROM
                        hlstats_Awards
                LEFT JOIN hlstats_Games ON
                        hlstats_Games.code = hlstats_Awards.game
                WHERE
                        hlstats_Games.hidden='0'
                ORDER BY
                        hlstats_Awards.game,
                        hlstats_Awards.awardType
        ");

        my $result = $g_db->DoQuery("
                SELECT
                        value,
                        DATE_SUB($date_base, INTERVAL 1 DAY)
                FROM
                        hlstats_Options
                WHERE
                        keyname = 'awards_d_date'
        ");

        if ($result->rows > 0)
        {
                my ($awards_d_date, $awards_d_date_new) = $result->fetchrow_array;
                
                $g_db->DoFastQuery("
                        UPDATE
                                hlstats_Options
                        SET
                                value='$awards_d_date_new'
                        WHERE
                                keyname='awards_d_date'
                ");
                
                print "(generating awards for $awards_d_date_new (previous: $awards_d_date))... ";
        }
        else
        {
                $g_db->DoFastQuery("
                        INSERT INTO
                                hlstats_Options
                                (
                                        keyname,
                                        value,
                                        opttype
                                )
                        VALUES
                        (
                                'awards_d_date',
                                DATE_SUB($date_base, INTERVAL 1 DAY),
                                2
                        )
                ");
        }

        $g_db->DoFastQuery("
                REPLACE INTO
                        hlstats_Options
                        (
                                keyname,
                                value,
                                opttype
                        )
                VALUES
                (
                        'awards_numdays',
                        $opt_numdays,
                        2
                )
        ");

        while( my ($awardId, $game, $awardType, $code) = $resultAwards->fetchrow_array )
        {
                my $table        = "";
                my $join         = "";
                my $matchfield   = "";
                my $playerfield  = "";
                my $resultDaily  = undef;
                my $resultGlobal = undef;

                if ($awardType eq "O")
                {
                        $table = "hlstats_Events_PlayerActions";
                        $join  = "LEFT JOIN hlstats_Actions ON hlstats_Actions.id = $table.actionId";
                        $matchfield = "hlstats_Actions.code";
                        $playerfield = "$table.playerId";
                }
                elsif ($awardType eq "W")
                {
                        $table = "hlstats_Events_Frags";
                        $playerfield = "$table.killerId";
                        if ($code eq "headshot") {
                                $join  = "";
                                $matchfield = "$table.headshot";
                                $code = 1;
                        } else {
                                $join  = "";
                                $matchfield = "$table.weapon";
                        }
                }
                elsif ($awardType eq "P")
                {
                        $table = "hlstats_Events_PlayerPlayerActions";
                        $join  = "LEFT JOIN hlstats_Actions ON hlstats_Actions.id = $table.actionId";
                        $matchfield = "hlstats_Actions.code";
                        $playerfield = "$table.playerId";
                }
                elsif ($awardType eq "V")
                {
                        $table = "hlstats_Events_PlayerPlayerActions";
                        $join  = "LEFT JOIN hlstats_Actions ON hlstats_Actions.id = $table.actionId";
                        $matchfield = "hlstats_Actions.code";
                        $playerfield = "$table.victimId";
                }
                
                if ($code eq "latency") {
                        $resultDaily = $g_db->DoQuery("
                                SELECT
                                        hlstats_Events_Latency.playerId,
                                        ROUND(ROUND(SUM(ping) / COUNT(ping), 0) / 2, 0) AS av_latency
                                FROM
                                        hlstats_Events_Latency
                                INNER JOIN
                                        hlstats_Servers ON
                                        hlstats_Servers.serverId=hlstats_Events_Latency.serverId
                                        AND hlstats_Servers.game=".$g_db->Quote($game)."
                                INNER JOIN
                                        hlstats_Players ON
                                        hlstats_Players.playerId = hlstats_Events_Latency.playerId
                                        AND hlstats_Players.hideranking=0
                                WHERE   
                                        hlstats_Events_Latency.eventTime < $date_base
                                        AND hlstats_Events_Latency.eventTime > DATE_SUB($date_base, INTERVAL $opt_numdays DAY)
                                GROUP BY
                                        hlstats_Events_Latency.playerId
                                ORDER BY 
                                        av_latency
                                LIMIT 1         
                        ");     
                        $resultGlobal = $g_db->DoQuery("
                                SELECT
                                        hlstats_Events_Latency.playerId,
                                        ROUND(ROUND(SUM(ping) / COUNT(ping), 0) / 2, 0) AS av_latency
                                FROM
                                        hlstats_Events_Latency
                                INNER JOIN
                                        hlstats_Servers ON
                                        hlstats_Servers.serverId=hlstats_Events_Latency.serverId
                                        AND hlstats_Servers.game=".$g_db->Quote($game)."
                                INNER JOIN
                                        hlstats_Players ON
                                        hlstats_Players.playerId = hlstats_Events_Latency.playerId
                                        AND hlstats_Players.hideranking=0
                                GROUP BY
                                        hlstats_Events_Latency.playerId
                                ORDER BY 
                                        av_latency
                                LIMIT 1         
                        ");     
                } elsif ($code eq "mostkills") {
                        $resultDaily = $g_db->DoQuery("
                                SELECT
                                        hlstats_Events_Frags.killerId,
                                        count(hlstats_Events_Frags.killerId) AS av_mostkills
                                FROM
                                        hlstats_Events_Frags
                                INNER JOIN
                                        hlstats_Servers ON
                                        hlstats_Servers.serverId=hlstats_Events_Frags.serverId
                                        AND hlstats_Servers.game=".$g_db->Quote($game)."
                                INNER JOIN
                                        hlstats_Players ON
                                        hlstats_Players.playerId = hlstats_Events_Frags.killerId
                                        AND hlstats_Players.hideranking=0
                                WHERE
                                        hlstats_Events_Frags.eventTime < $date_base
                                        AND hlstats_Events_Frags.eventTime > DATE_SUB($date_base, INTERVAL $opt_numdays DAY)
                                GROUP BY
                                        hlstats_Events_Frags.killerId
                                ORDER BY
                                        av_mostkills DESC
                                LIMIT 1"
                        );
                        $resultGlobal = $g_db->DoQuery("
                                SELECT
                                        hlstats_Events_Frags.killerId,
                                        count(hlstats_Events_Frags.killerId) AS av_mostkills
                                FROM
                                        hlstats_Events_Frags
                                INNER JOIN
                                        hlstats_Servers ON
                                        hlstats_Servers.serverId=hlstats_Events_Frags.serverId
                                        AND hlstats_Servers.game=".$g_db->Quote($game)."
                                INNER JOIN
                                        hlstats_Players ON
                                        hlstats_Players.playerId = hlstats_Events_Frags.killerId
                                        AND hlstats_Players.hideranking=0
                                GROUP BY
                                        hlstats_Events_Frags.killerId
                                ORDER BY
                                        av_mostkills DESC
                                LIMIT 1 
                        ");
                }
                elsif ($code eq "suicide") {
                        $resultDaily = $g_db->DoQuery("
                                SELECT
                                        hlstats_Events_Suicides.playerId,
                                        count(hlstats_Events_Suicides.playerId) AS av_suicides
                                FROM
                                        hlstats_Events_Suicides
                                INNER JOIN
                                        hlstats_Servers ON
                                        hlstats_Servers.serverId=hlstats_Events_Suicides.serverId
                                        AND hlstats_Servers.game=".$g_db->Quote($game)."
                                INNER JOIN
                                        hlstats_Players ON
                                        hlstats_Players.playerId = hlstats_Events_Suicides.playerId
                                        AND hlstats_Players.hideranking=0
                                WHERE
                                        hlstats_Events_Suicides.eventTime < $date_base
                                        AND hlstats_Events_Suicides.eventTime > DATE_SUB($date_base, INTERVAL $opt_numdays DAY)
                                GROUP BY
                                        hlstats_Events_Suicides.playerId
                                ORDER BY
                                        av_suicides DESC
                                LIMIT 1       
                        ");
                        $resultGlobal = $g_db->DoQuery("
                                SELECT
                                        hlstats_Events_Suicides.playerId,
                                        count(hlstats_Events_Suicides.playerId) AS av_suicides
                                FROM
                                        hlstats_Events_Suicides
                                INNER JOIN
                                        hlstats_Servers ON
                                        hlstats_Servers.serverId=hlstats_Events_Suicides.serverId
                                        AND hlstats_Servers.game=".$g_db->Quote($game)."
                                INNER JOIN
                                        hlstats_Players ON
                                        hlstats_Players.playerId = hlstats_Events_Suicides.playerId
                                        AND hlstats_Players.hideranking=0
                                GROUP BY
                                        hlstats_Events_Suicides.playerId
                                ORDER BY
                                        av_suicides DESC
                                LIMIT 1       
                        ");
                } elsif ($code eq "teamkills") {
                        $resultDaily = $g_db->DoQuery("
                                SELECT
                                        hlstats_Events_Teamkills.killerId,
                                        count(hlstats_Events_Teamkills.killerId) AS av_teamkills
                                FROM
                                        hlstats_Events_Teamkills
                                INNER JOIN
                                        hlstats_Servers ON
                                        hlstats_Servers.serverId=hlstats_Events_Teamkills.serverId
                                        AND hlstats_Servers.game=".$g_db->Quote($game)."
                                INNER JOIN
                                        hlstats_Players ON
                                        hlstats_Players.playerId = hlstats_Events_Teamkills.killerId
                                        AND hlstats_Players.hideranking=0
                                WHERE
                                        hlstats_Events_Teamkills.eventTime < $date_base
                                        AND hlstats_Events_Teamkills.eventTime > DATE_SUB($date_base, INTERVAL $opt_numdays DAY)
                                GROUP BY
                                        hlstats_Events_Teamkills.killerId
                                ORDER BY
                                        av_teamkills DESC
                                LIMIT 1       
                        ");
                        $resultGlobal = $g_db->DoQuery("
                                SELECT
                                        hlstats_Events_Teamkills.killerId,
                                        count(hlstats_Events_Teamkills.killerId) AS av_teamkills
                                FROM
                                        hlstats_Events_Teamkills
                                INNER JOIN
                                        hlstats_Servers ON
                                        hlstats_Servers.serverId=hlstats_Events_Teamkills.serverId
                                        AND hlstats_Servers.game=".$g_db->Quote($game)."
                                INNER JOIN
                                        hlstats_Players ON
                                        hlstats_Players.playerId = hlstats_Events_Teamkills.killerId
                                        AND hlstats_Players.hideranking=0
                                GROUP BY
                                        hlstats_Events_Teamkills.killerId
                                ORDER BY
                                        av_teamkills DESC
                                LIMIT 1       
                        ");
                } elsif ($code eq "bonuspoints") {
                        $resultDaily = $g_db->DoQuery("
                                SELECT
                                        actions.playerId,
                                        SUM(actions.bonus) AS av_bonuspoints
                                FROM
                                        (SELECT
                                                playerId, bonus, serverId, eventTime 
                                        FROM
                                                hlstats_Events_PlayerActions 
                                        WHERE
                                                eventTime < $date_base AND eventTime > DATE_SUB($date_base, INTERVAL $opt_numdays DAY)
                                        UNION ALL
                                        SELECT
                                                playerId, bonus, serverId, eventTime
                                        FROM
                                                hlstats_Events_PlayerPlayerActions
                                        WHERE
                                                eventTime < $date_base AND eventTime > DATE_SUB($date_base, INTERVAL $opt_numdays DAY)
                                        ) actions
                                INNER JOIN
                                        hlstats_Servers ON
                                        hlstats_Servers.serverId=actions.serverId
                                        AND hlstats_Servers.game=".$g_db->Quote($game)."
                                INNER JOIN
                                        hlstats_Players ON
                                        hlstats_Players.playerId = actions.playerId
                                        AND hlstats_Players.hideranking=0
                                GROUP BY
                                        playerId
                                ORDER BY
                                        av_bonuspoints DESC
                                LIMIT 1       
                        ");
                        $resultGlobal = $g_db->DoQuery("
                                SELECT
                                        actions.playerId,
                                        SUM(actions.bonus) AS av_bonuspoints
                                FROM
                                        (SELECT
                                                playerId, bonus, serverId, eventTime 
                                        FROM
                                                hlstats_Events_PlayerActions 
                                        UNION ALL
                                        SELECT
                                                playerId, bonus, serverId, eventTime
                                        FROM
                                                hlstats_Events_PlayerPlayerActions
                                        ) actions
                                INNER JOIN
                                        hlstats_Servers ON
                                        hlstats_Servers.serverId=actions.serverId
                                        AND hlstats_Servers.game=".$g_db->Quote($game)."
                                INNER JOIN
                                        hlstats_Players ON
                                        hlstats_Players.playerId = actions.playerId
                                        AND hlstats_Players.hideranking=0
                                GROUP BY
                                        playerId
                                ORDER BY
                                        av_bonuspoints DESC
                                LIMIT 1       
                        ");
                } elsif ($code eq "allsentrykills") {
                        $resultDaily = $g_db->DoQuery("
                                SELECT
                                        hlstats_Events_Frags.killerId,
                                        COUNT(hlstats_Events_Frags.weapon) AS awardcount
                                FROM
                                        hlstats_Events_Frags
                                INNER JOIN hlstats_Players ON
                                        hlstats_Players.playerId = hlstats_Events_Frags.killerId
                                        AND hlstats_Players.hideranking=0
                                WHERE
                                        hlstats_Events_Frags.eventTime < $date_base
                                        AND hlstats_Events_Frags.eventTime > DATE_SUB($date_base, INTERVAL $opt_numdays DAY)
                                        AND hlstats_Players.game=".$g_db->Quote($game)."
                                        AND hlstats_Events_Frags.weapon LIKE 'obj_sentrygun%'
                                GROUP BY
                                        hlstats_Events_Frags.killerId
                                ORDER BY
                                        awardcount DESC,
                                        hlstats_Players.skill DESC
                                LIMIT 1
                        ");
                        $resultGlobal = $g_db->DoQuery("
                                SELECT
                                        hlstats_Events_Frags.killerId,
                                        COUNT(hlstats_Events_Frags.weapon) AS awardcount
                                FROM
                                        hlstats_Events_Frags
                                INNER JOIN hlstats_Players ON
                                        hlstats_Players.playerId = hlstats_Events_Frags.killerId
                                        AND hlstats_Players.hideranking=0
                                WHERE
                                        hlstats_Players.game=".$g_db->Quote($game)."
                                        AND hlstats_Events_Frags.weapon LIKE 'obj_sentrygun%'
                                GROUP BY
                                        hlstats_Events_Frags.killerId
                                ORDER BY
                                        awardcount DESC,
                                        hlstats_Players.skill DESC
                                LIMIT 1
                        ");
                } else {
                        $resultDaily = $g_db->DoQuery("
                                SELECT
                                        $playerfield,
                                        COUNT($matchfield) AS awardcount
                                FROM
                                        $table
                                INNER JOIN hlstats_Players ON
                                        hlstats_Players.playerId = $playerfield
                                        AND hlstats_Players.hideranking=0
                                $join
                                WHERE
                                        $table.eventTime < $date_base
                                        AND $table.eventTime > DATE_SUB($date_base, INTERVAL $opt_numdays DAY)
                                        AND hlstats_Players.game=".$g_db->Quote($game)."
                                        AND $matchfield='$code'
                                GROUP BY
                                        $playerfield
                                ORDER BY
                                        awardcount DESC,
                                        hlstats_Players.skill DESC
                                LIMIT 1
                        ");
                        $resultGlobal = $g_db->DoQuery("
                                SELECT
                                        $playerfield,
                                        COUNT($matchfield) AS awardcount
                                FROM
                                        $table
                                INNER JOIN hlstats_Players ON
                                        hlstats_Players.playerId = $playerfield
                                        AND hlstats_Players.hideranking=0
                                $join
                                WHERE
                                        hlstats_Players.game=".$g_db->Quote($game)."
                                        AND $matchfield='$code'
                                GROUP BY
                                        $playerfield
                                ORDER BY
                                        awardcount DESC,
                                        hlstats_Players.skill DESC
                                LIMIT 1
                        ");
                }
                
                my ($d_winner_id, $d_winner_count) = $resultDaily->fetchrow_array;
                my ($g_winner_id, $g_winner_count) = $resultGlobal->fetchrow_array;
                
                if (!$d_winner_id || $d_winner_count < 1)
                {
                        $d_winner_id = "NULL";
                        $d_winner_count = "NULL";
                }
                if (!$g_winner_id || $g_winner_count < 1)
                {
                        $g_winner_id = "NULL";
                        $g_winner_count = "NULL";
                }
                
                if ($opt_verbose)
                {
                        print "  - $d_winner_id ($d_winner_count)\n";
                        print "  - $g_winner_id ($g_winner_count)\n";
                }
                
                $g_db->DoFastQuery("
                        UPDATE
                                hlstats_Awards
                        SET
                                d_winner_id=$d_winner_id,
                                d_winner_count=$d_winner_count,
                                g_winner_id=$g_winner_id,
                                g_winner_count=$g_winner_count
                        WHERE
                                awardId=$awardId
                ");
        }


        $g_db->DoFastQuery("
                INSERT IGNORE INTO 
                        hlstats_Players_Awards 
                SELECT 
                        value, awardId, d_winner_id, d_winner_count, game 
                FROM 
                        hlstats_Options INNER JOIN hlstats_Awards 
                WHERE 
                        keyname='awards_d_date' AND NOT ISNULL(d_winner_id);
                ");

        print "done\n";
}

sub DoRibbons
{
        print "++ Processing ribbons... ";
        
        my $result = $g_db->DoQuery("SELECT `code` FROM `hlstats_Games`;");
        while( my($game) = $result->fetchrow_array ) {

                $g_db->DoFastQuery("DELETE FROM hlstats_Players_Ribbons WHERE game=".$g_db->Quote($game).";");
                
                my $result2 = $g_db->DoQuery("
                        SELECT
                                `ribbonId`,
                                `awardCode`,
                                `awardCount`,
                                `special`
                        FROM
                                `hlstats_Ribbons`
                        WHERE
                                game=".$g_db->Quote($game)." AND
                                (special=0 OR special=2);
                        ");
                while ( my($ribbonid, $code, $count, $special) = $result2->fetchrow_array ) {
                        # scan players for each ribbon ID
                        my $result3;
                        if ($special == 2) {
                                # connection time
                                $result3 = $g_db->DoQuery("
                                        SELECT
                                                playerId,
                                                (connection_time/3600) AS CNT
                                        FROM
                                                hlstats_Players
                                        WHERE
                                                game=".$g_db->Quote($game)." 
                                                AND hlstats_Players.hideranking=0
                                                AND (connection_time/3600)>=".$count."
                                        ");
                        } else {
                                # awards ribbons
                                my $having = "CNT>=".$count;
                                $result3 = $g_db->DoQuery("
                                        SELECT
                                                hlstats_Players_Awards.playerId,
                                                COUNT(hlstats_Players_Awards.playerId) AS CNT
                                        FROM
                                                hlstats_Players_Awards
                                        INNER JOIN
                                                hlstats_Awards
                                        ON
                                                (hlstats_Awards.awardId=hlstats_Players_Awards.awardId AND
                                                hlstats_Awards.game=hlstats_Players_Awards.game)
                                        INNER JOIN
                                                hlstats_Players
                                        ON
                                                hlstats_Players.playerId = hlstats_Players_Awards.playerId
                                                AND hlstats_Players.hideranking=0
                                        WHERE
                                                hlstats_Players_Awards.game=".$g_db->Quote($game)." AND
                                                hlstats_Awards.code='".$code."' AND
                                                hlstats_Awards.awardType<>'V'
                                        GROUP BY
                                                hlstats_Players_Awards.playerId         
                                        HAVING
                                                ".$having."  
                                        ");
                        }

                        while (my($playerid, $cnt) = $result3->fetchrow_array) {
                                $g_db->DoFastQuery("
                                        INSERT INTO hlstats_Players_Ribbons
                                                (playerId, ribbonId, game)
                                        VALUES
                                                (".$playerid.",".$ribbonid.",".$g_db->Quote($game).")
                                        ");  
                        }
                }  

        }
        print "done\n";
}

sub DoGeoIP
{
        print "++ Looking up missing player locations... ";
        
        my $useGeoIPBinary = 0;
        my $gi = undef;
        my $dogeo = 0;
        my $cnt = 0;
        my $geoipfile = "";
        
        # Sanity checks to see if we can do geolocation updates
        my $result = $g_db->DoQuery("
                SELECT
                        value
                FROM
                        hlstats_Options
                WHERE
                        keyname='UseGeoIPBinary'
                        AND value > '0'
                LIMIT 1
        ");
        
        if ($result->rows > 0)
        {
                $useGeoIPBinary = 1;
                $geoipfile = "$opt_libdir/GeoLiteCity/GeoLiteCity.dat";
        }
        else
        {
                $useGeoIPBinary = 0;
        }
        
        if ($useGeoIPBinary == 0)
        {
                my $result = $g_db->DoQuery("SELECT locId FROM geoLiteCity_Blocks LIMIT 1;");
                if ($result->rows > 0)
                {
                        $dogeo = 1;
                }
                else
                {
                        PrintEvent("ERROR", "GeoIP method set to database but geoLiteCity tables are empty.", 1);
                }
        }
        elsif ($useGeoIPBinary == 1 && -r $geoipfile)
        {
                if ($opt_cpanelhack) {
                        my $home_dir = $ENV{ HOME };
                        my $base_module_dir = (-d "$home_dir/perl" ? "$home_dir/perl" : ( getpwuid($>) )[7] . '/perl/');
                        unshift @INC, map { $base_module_dir . $_ } @INC;
                }

                eval {
                  require Geo::IP::PurePerl;
                };
                import Geo::IP::PurePerl;
                
                $gi = Geo::IP::PurePerl->open($geoipfile, "GEOIP_STANDARD");
                if ($gi) 
                {
                        $dogeo = 1;
                }
                else
                {
                        PrintEvent("ERROR", "GeoIP method set to binary file lookup but $geoipfile errored while opening.", 1);
                        close($gi->{fh});
                }
        }
        else
        {
                &printEvent("ERROR", "GeoIP method set to binary file lookup but $geoipfile NOT FOUND", 1);
        }

                
        if ($dogeo) {
                sub ip2number {
                        my ($ipstr) = @_;
                        my @ip = split(/\./, $ipstr);
                        my $number = ($ip[0]*16777216) + ($ip[1]*65536) + ($ip[2]*256) + $ip[3];

                        return $number;
                }

                sub trim {
                        my $string = shift;
                        $string =~ s/^\s+|\s+$//g;
                        return $string;
                }
                $result = $g_db->DoQuery("SELECT playerId, lastAddress, lastName FROM hlstats_Players WHERE flag='' AND lastAddress<>'';");
                                
                while (my($pid, $address, $name) = $result->fetchrow_array) {
                        $address = trim($address);
                        next if ($address !~ /^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$/);
                        if ($opt_verbose)
                        {
                                print "Attempting to find location for: ".$name." (".$address.")\n";
                        }
                        my $number = ip2number($address);
                        my $update = 0;
                        my $foundflag = "";
                        my $foundcountry = "";
                        my $foundcity = "";
                        my $foundstate = "";
                        my $foundlat = 0;
                        my $foundlng = 0;
                        if ($useGeoIPBinary > 0) {
                                if ($opt_verbose)
                                {
                                        print "2 ".$pid." ".$address."\n";
                                }
                                my ($country_code, $country_code3, $country_name, $region, $city, $postal_code, $latitude, $longitude, $metro_code, $area_code) = $gi->get_city_record($address);
                                if ($longitude) {
                                        $foundflag = encode("utf8",$country_code);
                                        $foundcountry = encode("utf8",$country_name);
                                        $foundcity = encode("utf8",$city);
                                        $foundstate = encode("utf8",$region);
                                        $foundlat = $latitude;
                                        $foundlng = $longitude;
                                        $update++;
                                }
                        }
                        else
                        {
                                my $result2 = $g_db->DoQuery("SELECT locId FROM geoLiteCity_Blocks WHERE startIpNum<=".$number." AND endIpNum>=".$number." LIMIT 1;");
                                if ($result2->rows > 0) {
                                        my ($locid) = $result2->fetchrow_array;
                                        my $data = $g_db->DoQuery("SELECT city, region AS state, name AS country, country AS flag, latitude AS lat, longitude AS lng FROM geoLiteCity_Location a  inner join hlstats_Countries b ON a.country=b.flag WHERE locId=".$locid." LIMIT 1;");
                                        if ($data->rows > 0) {
                                                ($foundcity, $foundstate, $foundcountry, $foundflag, $foundlat, $foundlng) = $data->fetchrow_array;
                                                $update++;
                                        }
                                }
                        }
                        if ($update > 0)
                        {
                                $g_db->DoFastQuery("
                                        UPDATE
                                                hlstats_Players
                                        SET
                                                flag=".$g_db->Quote($foundflag).",
                                                country=".$g_db->Quote($foundcountry).",
                                                lat='".(($foundlat ne "")?$foundlat:undef)."',
                                                lng='".(($foundlng ne "")?$foundlng:undef)."',
                                                city=".$g_db->Quote($foundcity).",
                                                state=".$g_db->Quote($foundstate)."
                                        WHERE
                                                playerId=".$pid
                                );
                                $cnt++;
                        }
                }
        }
        printf ("done%s\n", (($cnt>0)?" (updated $cnt players)":""));
}

sub DoClans
{
        print "++ Reparsing player names to recalculate clan affiliations... ";
        
        my @clanpatterns = ();
        my $result = $g_db->DoQuery("
                SELECT
                        pattern,
                        position,
                        LENGTH(pattern) AS pattern_length
                FROM
                        hlstats_ClanTags
                ORDER BY
                        pattern_length DESC,
                        id
        ");
        
        while ( my($pattern, $position) = $result->fetchrow_array) {
                my $regpattern = quotemeta($pattern);
                $regpattern =~ s/([A-Za-z0-9]+[A-Za-z0-9_-]*)/\($1\)/; # to find clan name from tag
                $regpattern =~ s/A/./g;
                $regpattern =~ s/X/.?/g;
                if ($position eq "START") {
                        push(@clanpatterns, "^($regpattern).+");
                } elsif ($position eq "END") {
                        push(@clanpatterns, ".+($regpattern)\$");
                } elsif ($position eq "EITHER") {
                        push(@clanpatterns, "^($regpattern).+");
                        push(@clanpatterns, ".+($regpattern)\$");
                }
        }
        
        $result = $g_db->DoQuery("
                SELECT
                        playerId, lastName, game
                FROM
                        hlstats_Players
        ");
        
        while ( my($playerId, $name, $game) = $result->fetchrow_array)
        {
                my $clanTag  = "";
                my $clanName = "";
                my $clanId   = 0;
                
                foreach (@clanpatterns)
                {
                        $clanTag = "";
                        if ($name =~ /$_/i)
                        {
                                $clanTag  = $1;
                                $clanName = $2;
                                last;
                        }                       
                }
                if (!$clanTag)
                {
                        $g_db->DoCached("playerclan_clear", "UPDATE hlstats_Players SET clan=0 WHERE playerId=?", [$playerId]);
                        next;
                }
                
                my $query = "
                        SELECT
                                clanId
                        FROM
                                hlstats_Clans
                        WHERE
                                tag=? AND
                                game=?
                ";
                my $clanresult = $g_db->DoCached("clan_select", $query, [$clanTag, $game]);

                if ($clanresult->rows) {
                        my ($id) = $clanresult->fetchrow_array;
                        $clanresult->finish;
                        $clanId = $id;
                } else {
                        # The clan doesn't exist yet, so we create it.
                        $query = "
                                REPLACE INTO
                                        hlstats_Clans
                                        (
                                                tag,
                                                name,
                                                game
                                        )
                                VALUES
                                (
                                        ?,?,?
                                )
                        ";
                        $g_db->DoCached("clan_insertupdate", $query, [$clanTag, $clanName, $game]);
                        
                        $clanId = $g_db->{mysql_insertid};
                }
                $g_db->DoCached("playerclan_update", "UPDATE hlstats_Players SET clan=? WHERE playerId=?", [$clanId, $playerId]);
        }
        
        print "done\n";
}

sub DoPruning
{
        my $result = $g_db->DoQuery("SELECT `value` FROM hlstats_Options WHERE keyname='DeleteDays'");
        my ($deletedays) = $result->fetchrow_array;
        print "++ Cleaning up database: deleting events older than $deletedays days... ";
        
        while (my($eventTable) = each(%HLstats_Common::g_EventTables))
        {
                $g_db->DoFastQuery("
                        DELETE FROM
                                        hlstats_Events_$eventTable
                        WHERE
                                        eventTime < DATE_SUB(CURRENT_TIMESTAMP(), INTERVAL $deletedays DAY)
                        ");
        }
        
        print "done\n++ Cleaning up database: deleting player history older than $deletedays days... ";
        $g_db->DoFastQuery("
                DELETE FROM
                        hlstats_Players_History
                WHERE
                        eventTime < DATE_SUB(CURRENT_TIMESTAMP(), INTERVAL $deletedays DAY)
        ");
        
        print "done\n++ Cleaning up database: deleting stale trend samples... ";
        $g_db->DoFastQuery("
                DELETE FROM
                        hlstats_Trend
                WHERE
                        timestamp < (UNIX_TIMESTAMP() - 172800)
        ");
        print "done\n";
}

sub DoOptimize
{       
        print "++ Optimizing all tables... ";

        my $result = $g_db->DoQuery("SHOW TABLES");
        my @allTables;
        
        while ( my ($row) = $result->fetchrow_array ) {
                push(@allTables, $row);
        }
        
        foreach (@allTables) {
                $g_db->DoFastQuery("OPTIMIZE TABLE $_");
        }
        print "done\n";
}