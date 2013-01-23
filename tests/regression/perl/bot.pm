package MediaWiki::Bot;
# ABSTRACT: a MediaWiki bot framework written in Perl

use strict;
use warnings;

use HTML::Entities 3.28;
use URI::Escape 1.35;
use XML::Simple 2.16;
use Carp;
use URI::Escape qw(uri_escape_utf8);
use Digest::MD5 2.39 qw(md5_hex);
use Encode qw(encode_utf8);
use MediaWiki::API 0.20;

use Module::Pluggable search_path => [qw(MediaWiki::Bot::Plugin)], 'require' => 1;
foreach my $plugin (__PACKAGE__->plugins) {

    #print "Found plugin $plugin\n";
    $plugin->import();
}

our $VERSION = '3.2.6';

=head1 SYNOPSIS

    use MediaWiki::Bot;

    my $bot = MediaWiki::Bot->new({
        assert      => 'bot',
        protocol    => 'https',
        host        => 'secure.wikimedia.org',
        path        => 'wikipedia/meta/w',
        login_data  => { username => "Mike's bot account", password => "password" },
    });

    my $revid = $bot->get_last("User:Mike.lifeguard/sandbox", "Mike.lifeguard");
    print "Reverting to $revid\n" if defined($revid);
    $bot->revert('User:Mike.lifeguard', $revid, 'rvv');

=head1 DESCRIPTION

MediaWiki::Bot is a framework that can be used to write bots which interface
with the MediaWiki API (L<http://en.wikipedia.org/w/api.php>).

=head1 METHODS

=head2 new($options_hashref)

Calling MediaWiki::Bot->new() will create a new MediaWiki::Bot object.

=over 4

=item *
agent sets a custom useragent

=item *
assert sets a parameter for the AssertEdit extension (commonly 'bot'). Refer to L<http://mediawiki.org/wiki/Extension:AssertEdit>.

=item *
operator allows the bot to send you a message when it fails an assert, and will be integrated into the default useragent (which may not be used if you set agent yourself). The message will tell you that $useragent is logged out, so use a descriptive one if you set it.

=item *
maxlag allows you to set the maxlag parameter (default is the recommended 5s). Please refer to the MediaWiki documentation prior to changing this from the default.

=item *
protocol allows you to specify 'http' or 'https' (default is 'http'). This is commonly used with the domain and path settings below.

=item *
host sets the domain name of the wiki to connect to.

=item *
path sets the path to api.php (with no leading or trailing slash).

=item *
login_data is a hashref of credentials to pass to login(). See that section for a description.

=item *
debug is whether to provide debug output. 1 provides only error messages; 2 provides further detail on internal operations.

=back

For example:

    my $bot = MediaWiki::Bot->new({
        assert      => 'bot',
        protocol    => 'https',
        host        => 'secure.wikimedia.org',
        path        => 'wikipedia/meta/w',
        login_data  => { username => "Mike's bot account", password => "password" },
    });

For backward compatibility, you can specify up to three parameters:

    my $bot = MediaWiki::Bot->new('MediaWiki::Bot 2.3.1 (User:Mike.lifeguard)', $assert, $operator);

This deprecated form will never do auto-login or autoconfiguration.

=cut

sub new {
    my $package = shift;
    my $agent;
    my $assert;
    my $operator;
    my $maxlag;
    my $protocol;
    my $host;
    my $path;
    my $login_data;
    my $debug;

    if (ref $_[0] eq 'HASH') {
        $agent      = $_[0]->{'agent'};
        $assert     = $_[0]->{'assert'};
        $operator   = $_[0]->{'operator'};
        $maxlag     = $_[0]->{'maxlag'};
        $protocol   = $_[0]->{'protocol'};
        $host       = $_[0]->{'host'};
        $path       = $_[0]->{'path'};
        $login_data = $_[0]->{'login_data'};
        $debug      = $_[0]->{'debug'};
    }
    else {
        $agent    = shift;
        $assert   = shift;
        $operator = shift;
        $maxlag   = shift;
        $protocol = shift;
        $host     = shift;
        $path     = shift;
        $debug    = shift;
    }

    $assert   =~ s/[&?]assert=// if $assert; # Strip out param part, leaving just the value
    $operator =~ s/^User://i     if $operator;

    # Set defaults
    unless ($agent) {
        $agent  = "MediaWiki::Bot/$VERSION";
        $agent .= " (User:$operator)" if $operator;
    }

    my $self = bless({}, $package);
    $self->{errstr}   = '';
    $self->{assert}   = $assert;
    $self->{operator} = $operator;
    $self->{'debug'}  = $debug || 0;
    $self->{api}      = MediaWiki::API->new();
    $self->{api}->{ua}->agent($agent);

    # Set wiki (handles setting $self->{host} etc)
    $self->set_wiki({
            protocol => $protocol,
            host     => $host,
            path     => $path,
    });

    $self->{api}->{config}->{max_lag}         = $maxlag || 5;
    $self->{api}->{config}->{max_lag_delay}   = 1;
    $self->{api}->{config}->{retries}         = 5;
    $self->{api}->{config}->{max_lag_retries} = -1;
    $self->{api}->{config}->{retry_delay}     = 30;

    # Log-in, and maybe autoconfigure
    if ($login_data) {
        my $success = $self->login($login_data);
        if ($success) {
            return $self;
        }
        else {
            carp "Couldn't log in with supplied settings" if $self->{'debug'};
            return;
        }
    }

    return $self;
}

=head2 set_wiki($options)

Set what wiki to use. Host is the domain name; path is the path before api.php (usually 'w'); protocol is either 'http' or 'https'. For example:

    $bot->set_wiki(
        protocol    => 'https',
        host        => 'secure.wikimedia.org',
        path        => 'wikipedia/meta/w',
    );

For backward compatibility, you can specify up to two parameters in this deprecated form:

    $bot->set_wiki($host, $path);

If you don't set any parameter, it's previous value is used. If it has never been set, the default settings are 'http', 'en.wikipedia.org' and 'w'.

=cut

sub set_wiki {
    my $self = shift;
    my $host;
    my $path;
    my $protocol;

    if (ref $_[0] eq 'HASH') {
        $host     = $_[0]->{'host'};
        $path     = $_[0]->{'path'};
        $protocol = $_[0]->{'protocol'};
    }
    else {
        $host = shift;
        $path = shift;
    }

    # Set defaults
    $protocol = $self->{'protocol'} || 'http'             unless defined($protocol);
    $host     = $self->{'host'}     || 'en.wikipedia.org' unless defined($host);
    $path     = $self->{'path'}     || 'w'                unless defined($path);

    # Clean up the parts we will build a URL with
    $protocol =~ s,://$,,;
    if ($host =~ m,^(http|https)(://)?, && !$protocol) {
        $protocol = $1;
    }
    $host =~ s,^https?://,,;
    $host =~ s,/$,,;
    $path =~ s,/$,,;

    # Invalidate wiki-specific cached data
    if (   ((defined($self->{'host'})) and ($self->{'host'} ne $host))
        or ((defined($self->{'path'})) and ($self->{'path'} ne $path))
        or ((defined($self->{'protocol'})) and ($self->{'protocol'} ne $protocol))
    ) {
        delete $self->{'ns_data'} if $self->{'ns_data'};
    }

    $self->{protocol} = $protocol;
    $self->{host}     = $host;
    $self->{path}     = $path;

    $self->{api}->{config}->{api_url} = $path
        ? "$protocol://$host/$path/api.php"
        : "$protocol://$host/api.php"; # $path is '', so don't use http://domain.com//api.php
    warn "Wiki set to " . $self->{api}->{config}{api_url} . "\n" if $self->{'debug'} > 1;

    return 1;
}

=head2 login($login_hashref)

Logs the use $username in, optionally using $password. First, an attempt will be made to use cookies to log in. If this fails, an attempt will be made to use the password provided to log in, if any. If the login was successful, returns true; false otherwise.

    $bot->login({
        username => $username,
        password => $password,
    }) or die "Login failed";

Once logged in, attempt to do some simple auto-configuration. At present, this consists of:

=over 4

=item *

Warning if the account doesn't have the bot flag, and isn't a sysop account.

=item *

Setting the use of apihighlimits if the account has that userright.

=item *

Setting an appropriate default assert.

=back

You can skip this autoconfiguration by passing C<autoconfig =E<gt> 0>

=head3 Single User Login

On WMF wikis, C<do_sul> specifies whether to log in on all projects. The default is false. But even when false, you still get a CentralAuth cookie for, and are thus logged in on, all languages of a given domain (*.wikipedia.org, for example). When set, a login is done on each WMF domain so you are logged in on all ~800 content wikis. Since C<*.wikimedia.org> is not possible, we explicitly include meta, commons, incubator, and wikispecies. When C<do_sul> is set, the return is the number of domains that login was successful for. This allows callers to do the following:

    $bot->login({
        username    => $username,
        password    => $password,
        do_sul      => 1,
    }) or die "SUL failed";

For backward compatibility, you can call this as

    $bot->login($username, $password);

This deprecated form will never do autoconfiguration or SUL login.

If you need to supply basic auth credentials, pass a hashref of data as described by L<LWP::UserAgent>:

    $bot->login({
        username    => $username,
        password    => $password,
        basic_auth  => {    netloc  => "private.wiki.com:80",
                            realm   => "Authentication Realm",
                            uname   => "Basic auth username",
                            pass    => "password",
                        }
    }) or die "Couldn't log in";

=cut

sub login {
    my $self = shift;
    my $username;
    my $password;
    my $lgdomain;
    my $autoconfig;
    my $basic_auth;
    my $do_sul;
    if (ref $_[0] eq 'HASH') {
        $username   = $_[0]->{'username'};
        $password   = $_[0]->{'password'};
        $autoconfig = defined($_[0]->{'autoconfig'}) ? $_[0]->{'autoconfig'} : 1;
        $basic_auth = $_[0]->{'basic_auth'};
        $do_sul     = $_[0]->{'do_sul'} || 0;
        $lgdomain   = $_[0]->{'lgdomain'};
    }
    else {
        $username   = shift;
        $password   = shift;
        $autoconfig = 0;
        $do_sul     = 0;
    }
    $self->{'username'} = $username;    # Remember who we are

    # Handle basic auth first, if needed
    if ($basic_auth) {
        warn "Applying basic auth credentials" if $self->{'debug'} > 1;
        $self->{api}->{ua}->credentials(
            $basic_auth->{'netloc'},
            $basic_auth->{'realm'},
            $basic_auth->{'uname'},
            $basic_auth->{'pass'}
        );
    }
    $do_sul = 0 if (
        ($self->{'protocol'} eq 'https') and
        ($self->{'host'} eq 'secure.wikimedia.org') );

    if ($do_sul) {
        my $debug    = $self->{'debug'};   # Remember this for later
        my $host     = $self->{'host'};
        my $path     = $self->{'path'};
        my $protocol = $self->{'protocol'};

        $self->{'debug'} = 0;           # Turn off debugging for these internal calls
        my @logins;                     # Keep track of our successes
        my @WMF_projects = qw(
            en.wikipedia.org
            en.wiktionary.org
            en.wikibooks.org
            en.wikinews.org
            en.wikiquote.org
            en.wikisource.org
            en.wikiversity.org
            meta.wikimedia.org
            commons.wikimedia.org
            species.wikimedia.org
            incubator.wikimedia.org
        );

        SUL: foreach my $project (@WMF_projects) {
            print STDERR "Logging in on $project..." if $debug > 1;
            $self->set_wiki({
                host    => $project,
            });
            my $success = $self->login({
                username    => $username,
                password    => $password,
                lgdomain    => $lgdomain,
                do_sul      => 0,
                autoconfig  => 0,
            });
            warn ($success ? " OK\n" : " FAILED\n") if $debug > 1;
            push(@logins, $success);
        }
        $self->set_wiki({           # Switch back to original wiki
            protocol => $protocol,
            host     => $host,
            path     => $path,
        });

        my $sum = 0;
        $sum += $_ for @logins;
        my $total = scalar @WMF_projects;
        warn "$sum/$total logins succeeded\n" if $debug > 1;
        $self->{'debug'} = $debug; # Reset debug to it's old value

        return $sum;
    }

    my $cookies = ".mediawiki-bot-$username-cookies";
    if (-r $cookies) {
        $self->{api}->{ua}->{cookie_jar}->load($cookies);
        $self->{api}->{ua}->{cookie_jar}->{ignore_discard} = 1;

        my $logged_in = $self->_is_loggedin();
        if ($logged_in) {
            $self->_do_autoconfig() if $autoconfig;
            warn "Logged in successfully with cookies" if $self->{'debug'} > 1;
            return 1; # If we're already logged in, nothing more is needed
        }
    }

    unless ($password) {
        carp "No login cookies available, and no password to continue with authentication" if $self->{'debug'};
        return 0;
    }

    my $res = $self->{api}->api({
        action      => 'login',
        lgname      => $username,
        lgpassword  => $password,
        lgdomain    => $lgdomain
    }) or return $self->_handle_api_error();
    $self->{api}->{ua}->{cookie_jar}->extract_cookies($self->{api}->{response});
    $self->{api}->{ua}->{cookie_jar}->save($cookies) if (-w($cookies) or -w('.'));

    if ($res->{'login'}->{'result'} eq 'NeedToken') {
        my $token = $res->{'login'}->{'token'};
        $res = $self->{api}->api({
            action      => 'login',
            lgname      => $username,
            lgpassword  => $password,
            lgdomain    => $lgdomain,
            lgtoken     => $token,
        }) or return $self->_handle_api_error();

        $self->{api}->{ua}->{cookie_jar}->extract_cookies($self->{api}->{response});
        $self->{api}->{ua}->{cookie_jar}->save($cookies) if (-w($cookies) or -w('.'));
    }

    if ($res->{'login'}->{'result'} eq 'Success') {
        if ($res->{'login'}->{'lgusername'} eq $self->{'username'}) {
            $self->_do_autoconfig() if $autoconfig;
            warn "Logged in successfully with password" if $self->{'debug'} > 1;
        }
    }

    return (
        (defined($res->{'login'}->{'lgusername'})) and
        (defined($res->{'login'}->{'result'})) and
        ($res->{'login'}->{'lgusername'} eq $self->{'username'}) and
        ($res->{'login'}->{'result'} eq 'Success')
    );
}

=head2 set_highlimits($flag)

Tells MediaWiki::Bot to start/stop using APIHighLimits for certain queries.

    $bot->set_highlimits(1);

=cut

sub set_highlimits {
    my $self       = shift;
    my $highlimits = defined($_[0]) ? shift : 1;

    $self->{highlimits} = $highlimits;
    return 1;
}

=head2 logout()

The logout procedure deletes the login tokens and other browser cookies.

    $bot->logout();

=cut

sub logout {
    my $self = shift;

    my $hash = {
        action => 'logout',
    };
    $self->{api}->api($hash);
    return 1;
}

=head2 edit($options_hashref)

Puts text on a page. If provided, use a specified edit summary, mark the edit as minor, as a non-bot edit, or add an assertion. Set section to edit a single section instead of the whole page. An MD5 hash is sent to guard against data corruption while in transit.

    my $text = $bot->get_text('My page');
    $text .= "\n\n* More text\n";
    $bot->edit({
        page    => 'My page',
        text    => $text,
        summary => 'Adding new content',
        section => 'new',
    });

You can also call this using the deprecated form:

    $bot->edit($page, $text, $summary, $is_minor, $assert, $markasbot);

=cut

sub edit {
    my $self = shift;
    my $page;
    my $text;
    my $summary;
    my $is_minor;
    my $assert;
    my $markasbot;
    my $section;

    if (ref $_[0] eq 'HASH') {
        $page      = $_[0]->{'page'};
        $text      = $_[0]->{'text'};
        $summary   = $_[0]->{'summary'};
        $is_minor  = $_[0]->{'is_minor'};
        $assert    = $_[0]->{'assert'};
        $markasbot = $_[0]->{'markasbot'};
        $section   = $_[0]->{'section'};
    }
    else {
        $page      = shift;
        $text      = shift;
        $summary   = shift;
        $is_minor  = shift;
        $assert    = shift;
        $markasbot = shift;
        $section   = shift;
    }

    # Set defaults
    $summary = 'BOT: Changing page text' unless $summary;
    if ($assert) {
        $assert =~ s/^[&?]assert=//;
    }
    else {
        $assert = $self->{'assert'};
    }
    $is_minor  = 1 unless defined($is_minor);
    $markasbot = 1 unless defined($markasbot);

    my ($edittoken, $lastedit, $tokentime) = $self->_get_edittoken($page);
    return $self->_handle_api_error() unless $edittoken;

    my $hash = {
        action         => 'edit',
        title          => $page,
        token          => $edittoken,
        text           => $text,
        md5            => md5_hex(encode_utf8($text)),    # Guard against data corruption
                                                          # Pass only bytes to md5_hex()
        summary        => $summary,
        basetimestamp  => $lastedit,                      # Guard against edit conflicts
        starttimestamp => $tokentime,                     # Guard against the page being deleted/moved
        bot            => $markasbot,
        assert         => $assert,
        minor          => $is_minor,
        section        => $section,
    };
    delete $hash->{'section'} unless defined($section);

    my $res = $self->{'api'}->api($hash);
    return $self->_handle_api_error() unless $res;
    if ($res->{'edit'}->{'result'} && $res->{'edit'}->{'result'} eq 'Failure') {
        if ($self->{'operator'}) {
            my $optalk = $self->get_text('User talk:' . $self->{'operator'});
            if (defined($optalk)) {
                carp "Sending warning!" if $self->{'debug'};
                if ($self->{'username'}) {
                    $self->edit(
                        page     => "User talk:$self->{'operator'}",
                        text     => $optalk
                                    . "\n\n==Error with $self->{'username'}==\n"
                                    . "$self->{'username'} needs to be logged in! ~~~~",
                        summary  => 'bot issue',
                        is_minor => 0,
                        assert   => '',
                    );
                    croak "$self->{'username'} got logged out" if $self->{'debug'};
                }
                else { # The bot wasn't ever supposed to be logged in
                    $self->edit(
                        page     => "User talk:$self->{'operator'}",
                        text     => $optalk
                                    . "\n\n==Error with your bot==\n"
                                    . "Your bot encountered an error. ~~~~",
                        summary  => 'bot issue',
                        is_minor => 0,
                        assert   => '',
                    );
                    croak "Bot encountered an error while editing" if $self->{'debug'};
                }
            }
        }
        return;
    }
    return $res;
}

=head2 move($from, $to, $reason, $options_hashref)

This moves a page from $from to $to. If you wish to specify more options (like whether to suppress creation of a redirect), use $options_hashref.

=over 4

=item *
movetalk specifies whether to attempt to the talk page.

=item *
noredirect specifies whether to suppress creation of a redirect.

=item *
movesubpages specifies whether to move subpages, if applicable.

=item *
watch and unwatch add or remove the page and the redirect from your watchlist.

=item *
ignorewarnings ignores warnings.

=back

    my @pages = ("Humor", "Rumor");
    foreach my $page (@pages) {
        my $to = $page;
        $to =~ s/or$/our/;
        $bot->move($page, $to, "silly 'merricans");
    }

=cut

sub move {
    my $self   = shift;
    my $from   = shift;
    my $to     = shift;
    my $reason = shift;
    my $opts   = shift;

    my $hash = {
        action => 'move',
        from   => $from,
        to     => $to,
        reason => $reason,
    };
    $hash->{'movetalk'}   = $opts->{'movetalk'}   if defined($opts->{'movetalk'});
    $hash->{'noredirect'} = $opts->{'noredirect'} if defined($opts->{'noredirect'});

    my $res = $self->{api}->edit($hash);
    return $self->_handle_api_error() unless $res;
    return $res; # should we return something more useful?
}

=head2 get_history($pagename[,$limit])

Returns an array containing the history of the specified page, with $limit number of revisions (default is as many as possible). The array structure contains 'revid', 'user', 'comment', 'timestamp_date', and 'timestamp_time'.

=cut

sub get_history {
    my $self      = shift;
    my $pagename  = shift;
    my $limit     = shift || 'max';
    my $rvstartid = shift;
    my $direction = shift;

    my @return;
    my @revisions;

    my $hash = {
        action  => 'query',
        prop    => 'revisions',
        titles  => $pagename,
        rvprop  => 'ids|timestamp|user|comment',
        rvlimit => $limit
    };

    $hash->{rvstartid} = $rvstartid if ($rvstartid);
    $hash->{direction} = $direction if ($direction);

    my $res = $self->{api}->api($hash);
    return $self->_handle_api_error() unless $res;
    my ($id) = keys %{ $res->{query}->{pages} };
    my $array = $res->{query}->{pages}->{$id}->{revisions};

    foreach my $hash (@{$array}) {
        my $revid = $hash->{revid};
        my $user  = $hash->{user};
        my ($timestamp_date, $timestamp_time) = split(/T/, $hash->{timestamp});
        $timestamp_time =~ s/Z$//;
        my $comment = $hash->{comment};
        push(
            @return,
            {
                revid          => $revid,
                user           => $user,
                timestamp_date => $timestamp_date,
                timestamp_time => $timestamp_time,
                comment        => $comment,
            });
    }
    return @return;
}

=head2 get_text($pagename,[$revid,$section_number])

Returns an the wikitext of the specified page. If $revid is defined, it will return the text of that revision; if $section_number is defined, it will return the text of that section. A blank page will return wikitext of "" (which evaluates to false in Perl, but is defined); a nonexistent page will return undef (which also evaluates to false in Perl, but is obviously undefined). You can distinguish between blank and nonexistent by using defined():

    my $wikitext = $bot->get_text('Page title');
    print "Wikitext: $wikitext\n" if defined $wikitext;

=cut

sub get_text {
    my $self     = shift;
    my $pagename = shift;
    my $revid    = shift;
    my $section  = shift;

    my $hash = {
        action => 'query',
        titles => $pagename,
        prop   => 'revisions',
        rvprop => 'content',
    };
    $hash->{rvstartid} = $revid   if ($revid);
    $hash->{rvsection} = $section if ($section);

    my $res = $self->{api}->api($hash);
    return $self->_handle_api_error() unless $res;
    my ($id, $data) = %{ $res->{query}->{pages} };
    if ($id == -1) {    # Page doesn't exist
        return;
    }
    else {              # Page exists
        my $wikitext = $data->{revisions}[0]->{'*'};
        return $wikitext;
    }
}

=head2 get_id($pagename)

Returns the id of the specified page. Returns undef if page does not exist.

    my $pageid = $bot->get_id("Main Page");
    croak "Page doesn't exist\n" if !defined($pageid);

=cut

sub get_id {
    my $self     = shift;
    my $pagename = shift;

    my $hash = {
        action => 'query',
        titles => $pagename,
    };

    my $res = $self->{api}->api($hash);
    return $self->_handle_api_error() unless $res;
    my ($id, $data) = %{ $res->{query}->{pages} };
    if ($id == -1) {
        return;
    }
    else {
        return $id;
    }
}

=head2 get_pages(\@pages)

Returns the text of the specified pages in a hashref. Content of undef means page does not exist. Also handles redirects or article names that use namespace aliases.

    my @pages = ('Page 1', 'Page 2', 'Page 3');
    my $thing = $bot->get_pages(\@pages);
    foreach my $page (keys %$thing) {
        my $text = $thing->{$page};
        print "$text\n" if defined($text);
    }

=cut

sub get_pages {
    my $self  = shift;
    my @pages = (ref $_[0] eq 'ARRAY') ? @{$_[0]} : @_;
    my %return;

    my $hash = {
        action => 'query',
        titles => join('|', @pages),
        prop   => 'revisions',
        rvprop => 'content',
    };

    my $diff;    # Used to track problematic article names
    map { $diff->{$_} = 1; } @pages;

    my $res = $self->{api}->api($hash);
    return $self->_handle_api_error() unless $res;

    foreach my $id (keys %{ $res->{query}->{pages} }) {
        my $page = $res->{'query'}->{'pages'}->{$id};
        if ($diff->{ $page->{'title'} }) {
            $diff->{ $page->{'title'} }++;
        }
        else {
            next;
        }

        if (defined($page->{'missing'})) {
            $return{ $page->{'title'} } = undef;
            next;
        }
        if (defined($page->{'revisions'})) {
            my $revisions = @{ $page->{'revisions'} }[0]->{'*'};
            if (!defined $revisions) {
                $return{ $page->{'title'} } = $revisions;
            }
            elsif (length($revisions) < 150 && $revisions =~ m/\#REDIRECT\s\[\[([^\[\]]+)\]\]/) {    # FRAGILE!
                my $redirect_to = $1;
                $return{ $page->{'title'} } = $self->get_text($redirect_to);
            }
            else {
                $return{ $page->{'title'} } = $revisions;
            }
        }
    }

    # Based on api.php?action=query&meta=siteinfo&siprop=namespaces|namespacealiases
    # Should be done on an as-needed basis! This is only correct for enwiki (and
    # it is probably incomplete anyways, or will be eventually).
    my $expand = {
        'WP'         => 'Wikipedia',
        'WT'         => 'Wikipedia talk',
        'Image'      => 'File',
        'Image talk' => 'File talk',
    };

    # Only for those article names that remained after the first part
    # If we're here we are dealing most likely with a WP:CSD type of article name
    for my $title (keys %$diff) {
        if ($diff->{$title} == 1) {
            my @pieces = split(/:/, $title);
            if (@pieces > 1) {
                $pieces[0] = ($expand->{ $pieces[0] } || $pieces[0]);
                my $v = $self->get_text(join ':', @pieces);
                warn "Detected article name that needed expanding $title\n" if $self->{'debug'} > 1;

                $return{$title} = $v;
                if ($v =~ m/\#REDIRECT\s\[\[([^\[\]]+)\]\]/) {
                    $v = $self->get_text($1);
                    $return{$title} = $v;
                }
            }
        }
    }
    return \%return;
}

=head2 revert($pagename, $revid[,$summary])

Reverts the specified page to $revid, with an edit summary of $summary. A default edit summary will be used if $summary is omitted.

    my $revid = $bot->get_last("User:Mike.lifeguard/sandbox", "Mike.lifeguard");
    print "Reverting to $revid\n" if defined($revid);
    $bot->revert('User:Mike.lifeguard', $revid, 'rvv');


=cut

sub revert {
    my $self     = shift;
    my $pagename = shift;
    my $revid    = shift;
    my $summary  = shift || "Reverting to old revision $revid";

    my $text = $self->get_text($pagename, $revid);
    my $res = $self->edit({
        page    => $pagename,
        text    => $text,
        summary => $summary,
    });

    return $res;
}

=head2 undo($pagename, $revid[,$summary[,$after]])

Reverts the specified $revid, with an edit summary of $summary, using the undo function. To undo all revisions from $revid up to but not including this one, set $after to another revid. If not set, just undo the one revision ($revid).

=cut

sub undo {
    my $self    = shift;
    my $page    = shift;
    my $revid   = shift;
    my $summary = shift || "Reverting revision #$revid";
    my $after   = shift;
    $summary = "Reverting edits between #$revid & #$after" if defined($after);    # Is that clear? Correct?

    my ($edittoken, $basetimestamp, $starttimestamp) = $self->_get_edittoken($page);
    my $hash = {
        action         => 'edit',
        title          => $page,
        undo           => $revid,
        undoafter      => $after,
        summary        => $summary,
        token          => $edittoken,
        starttimestamp => $starttimestamp,
        basetimestamp  => $basetimestamp,
    };

    my $res = $self->{api}->api($hash);
    return $self->_handle_api_error() unless $res;
    return $res;
}

=head2 get_last($page, $user)

Returns the revid of the last revision to $page not made by $user. undef is returned if no result was found, as would be the case if the page is deleted.

    my $revid = $bot->get_last("User:Mike.lifeguard/sandbox", "Mike.lifeguard");
    if defined($revid) {
        print "Reverting to $revid\n";
        $bot->revert('User:Mike.lifeguard', $revid, 'rvv');
    }

=cut

sub get_last {
    my $self = shift;
    my $page = shift;
    my $user = shift;

    my $revertto = 0;

    my $res = $self->{api}->api({
            action        => 'query',
            titles        => $page,
            prop          => 'revisions',
            rvlimit       => 1,
            rvprop        => 'ids|user',
            rvexcludeuser => $user,
    });
    return $self->_handle_api_error() unless $res;
    my ($id, $data) = %{ $res->{query}->{pages} };
    my $revid = $data->{'revisions'}[0]->{'revid'};
    return $revid;
}

=head2 update_rc($limit[,$options_hashref])

B<Note:> C<update_rc()> is deprecated in favour of C<recentchanges()>, which
returns all available data, including rcid.

Returns an array containing the Recent Changes to the wiki Main
namespace. The array structure contains 'title', 'revid', 'old_revid',
and 'timestamp'. The $options_hashref is the same as described in the
section on linksearch().

    my @rc = $bot->update_rc(5);
    foreach my $hashref (@rc) {
        my $title = $hash->{'title'};
        print "$title\n";
    }

    # Or, use a callback for incremental processing:
    my $options = { hook => \&mysub, };
    $bot->update_rc($options);
    sub mysub {
        my ($res) = @_;
        foreach my $hashref (@$res) {
            my $page = $hashref->{'title'};
            print "$page\n";
        }
    }

=cut

sub update_rc {
    my $self    = shift;
    my $limit   = shift || 'max';
    my $options = shift;

    my $hash = {
        action      => 'query',
        list        => 'recentchanges',
        rcnamespace => 0,
        rclimit     => $limit,
    };
    $options->{'max'} = 1 unless $options->{'max'};

    my $res = $self->{api}->list($hash, $options);
    return $self->_handle_api_error() unless $res;
    return 1 if (!ref $res);    # Not a ref when using callback
    my @rc_table;
    foreach my $hash (@{$res}) {
        push(
            @rc_table,
            {
                title     => $hash->{'title'},
                revid     => $hash->{'revid'},
                old_revid => $hash->{'old_revid'},
                timestamp => $hash->{'timestamp'},
            });
    }
    return @rc_table;
}

=head2 recentchanges($ns, $limit, $options_hashref)

Returns an array of hashrefs containing recentchanges data. That hashref
might contain the following keys:

=over 4

=item ns - the namespace number

=item revid

=item old_revid

=item timestamp

=item rcid - can be used with C<patrol()>

=item pageid

=item type - one of edit, new, log, and maybe more

=item title

=back

By default, the main namespace is used, and limit is set to 50. Pass an
arrayref of namespace numbers to get results from several namespaces.

The $options_hashref is the same as described in the section on linksearch().

    my @rc = $bot->update_rc(4, 10);
    foreach my $hashref (@rc) {
        print $hashref->{'title'} . "\n";
    }

    # Or, use a callback for incremental processing:
    $bot->update_rc(0, 500, { hook => \&mysub });
    sub mysub {
        my ($res) = @_;
        foreach my $hashref (@$res) {
            my $page = $hashref->{'title'};
            print "$page\n";
        }
    }

=cut

sub recentchanges {
    my $self    = shift;
    my $ns      = shift || 0;
    my $limit   = defined($_[0]) ? shift : 50;
    my $options = shift;
    $ns = join('|', @$ns) if ref $ns eq 'ARRAY';

    my $hash = {
        action      => 'query',
        list        => 'recentchanges',
        rcnamespace => $ns,
        rclimit     => $limit,
    };
    $options->{'max'} = 1 unless $options->{'max'};

    my $res = $self->{api}->list($hash, $options);
    return $self->_handle_api_error() unless $res;
    return 1 unless ref $res;    # Not a ref when using callback
    return @$res;
}

=head2 what_links_here($page[,$filter[,$ns[,$options]]])

Returns an array containing a list of all pages linking to $page. The array structure contains 'title' and 'redirect' is defined if the title is a redirect. $filter can be one of: all (default), redirects (list only redirects), nonredirects (list only non-redirects). $ns is a namespace number to search (pass an arrayref to search in multiple namespaces). $options is a hashref as described by MediaWiki::API: Set max to limit the number of queries performed. Set hook to a subroutine reference to use a callback hook for incremental processing. Refer to the section on linksearch() for examples.

A typical query:

    my @links = $bot->what_links_here("Meta:Sandbox", undef, 1, {hook=>\&mysub});
    sub mysub{
        my ($res) = @_;
        foreach my $hash (@$res) {
            my $title = $hash->{'title'};
            my $is_redir = $hash->{'redirect'};
            print "Redirect: $title\n" if $is_redir;
            print "Page: $title\n" unless $is_redir;
        }
    }

Transclusions are no longer handled by what_links_here() - use list_transcludes() instead.

=cut

sub what_links_here {
    my $self    = shift;
    my $page    = shift;
    my $filter  = shift;
    my $ns      = shift;
    my $options = shift;

    $ns = join('|', @$ns) if (ref $ns eq 'ARRAY');    # Allow array of namespaces
    if (defined($filter) and $filter =~ m/(all|redirects|nonredirects)/) {    # Verify $filter
        $filter = $1;
    }

    # http://en.wikipedia.org/w/api.php?action=query&list=backlinks&bltitle=template:tlx
    my $hash = {
        action      => 'query',
        list        => 'backlinks',
        bltitle     => $page,
        blnamespace => $ns,
        bllimit     => 'max',
    };
    $hash->{'blfilterredir'} = $filter if $filter;
    $options->{'max'} = 1 unless $options->{'max'};

    my $res = $self->{api}->list($hash, $options);
    return $self->_handle_api_error() unless $res;
    return 1 if (!ref $res);    # When using a callback hook, this won't be a reference
    my @links;
    foreach my $hashref (@$res) {
        my $title    = $hashref->{'title'};
        my $redirect = defined($hashref->{'redirect'});
        push @links, { title => $title, redirect => $redirect };
    }

    return @links;
}

=head2 list_transclusions($page[,$filter[,$ns[,$options]]])

Returns an array containing a list of all pages transcluding $page. The array structure contains 'title' and 'redirect' is defined if the title is a redirect. $filter can be one of: all (default), redirects (list only redirects), nonredirects (list only non-redirects). $ns is a namespace number to search (pass an arrayref to search in multiple namespaces). $options is a hashref as described by MediaWiki::API: Set max to limit the number of queries performed. Set hook to a subroutine reference to use a callback hook for incremental processing. Refer to the section on linksearch() or what_links_here() for examples.

A typical query:

    $bot->list_transclusions("Template:Tlx", undef, 4, {hook => \&mysub});
    sub mysub{
        my ($res) = @_;
        foreach my $hash (@$res) {
            my $title = $hash->{'title'};
            my $is_redir = $hash->{'redirect'};
            print "Redirect: $title\n" if $is_redir;
            print "Page: $title\n" unless $is_redir;
        }
    }

=cut

sub list_transclusions {
    my $self    = shift;
    my $page    = shift;
    my $filter  = shift;
    my $ns      = shift;
    my $options = shift;

    $ns = join('|', @$ns) if (ref $ns eq 'ARRAY');
    if (defined($filter) and $filter =~ m/(all|redirects|nonredirects)/) {    # Verify $filter
        $filter = $1;
    }

    # http://en.wikipedia.org/w/api.php?action=query&list=embeddedin&eititle=Template:Stub
    my $hash = {
        action      => 'query',
        list        => 'embeddedin',
        eititle     => $page,
        einamespace => $ns,
        eilimit     => 'max',
    };
    $hash->{'eifilterredir'} = $filter if $filter;
    $options->{'max'} = 1 unless $options->{'max'};

    my $res = $self->{api}->list($hash, $options);
    return $self->_handle_api_error() unless $res;
    return 1 if (!ref $res);    # When using a callback hook, this won't be a reference
    my @links;
    foreach my $hashref (@$res) {
        my $title    = $hashref->{'title'};
        my $redirect = defined($hashref->{'redirect'});
        push @links, { title => $title, redirect => $redirect };
    }

    return @links;
}

=head2 get_pages_in_category($category_name[,$options_hashref])

Returns an array containing the names of all pages in the specified category (include Category: prefix). Does not recurse into sub-categories.

    my @pages = $bot->get_pages_in_category("Category:People on stamps of Gabon");
    print "The pages in Category:People on stamps of Gabon are:\n@pages\n";

The options hashref is as described in the section on linksearch(). Use { max => 0 } to get all results.

=cut

sub get_pages_in_category {
    my $self     = shift;
    my $category = shift;
    my $options  = shift;

    if ($category =~ m/:/) {    # It might have a namespace name
        my ($cat, $title) = split(/:/, $category, 2);
        if ($cat ne 'Category') {    # 'Category' is a canonical name for ns14
            my $ns_data     = $self->_get_ns_data();
            my $cat_ns_name = $ns_data->{'14'};        # ns14 gives us the localized name for 'Category'
            if ($cat ne $cat_ns_name) {
                $category = "$cat_ns_name:$category";
            }
        }
    }
    else {                                             # Definitely no namespace name, since there's no colon
        $category = "Category:$category";
    }
    warn "Category to fetch is [[$category]]" if $self->{'debug'} > 1;

    my $hash = {
        action  => 'query',
        list    => 'categorymembers',
        cmtitle => $category,
        cmlimit => 'max',
    };
    $options->{'max'} = 1 unless defined($options->{'max'});
    delete($options->{'max'}) if $options->{'max'} == 0;

    my $res = $self->{api}->list($hash, $options);
    return 1 if (!ref $res);    # Not a hashref when using callback
    return $self->_handle_api_error() unless $res;
    my @pages;
    foreach my $hash (@$res) {
        my $title = $hash->{'title'};
        push @pages, $title;
    }
    return @pages;
}

=head2 get_all_pages_in_category($category_name[,$options_hashref])

Returns an array containing the names of ALL pages in the specified category (include the Category: prefix), including sub-categories. The $options_hashref is the same as described for get_pages_in_category().

=cut

{    # Instead of using the state pragma, use a bare block
    my %data;

    sub get_all_pages_in_category {
        my $self          = shift;
        my $base_category = shift;
        my $options       = shift;
        $options->{'max'} = 0 unless defined($options->{'max'});

        my @first = $self->get_pages_in_category($base_category, $options);
        %data = () unless $_[0];    # This is a special flag for internal use.
                                    # It marks a call to this method as being
                                    # internal. Since %data is a fake state variable,
                                    # it needs to be cleared for every *external*
                                    # call, but not cleared when the call is recursive.

        my $ns_data     = $self->_get_ns_data();
        my $cat_ns_name = $ns_data->{'14'};

        foreach my $page (@first) {
            if ($page =~ m/^$cat_ns_name:/) {
                if (!exists($data{$page})) {
                    $data{$page} = '';
                    my @pages = $self->get_all_pages_in_category($page, $options, 1);
                    foreach (@pages) {
                        $data{$_} = '';
                    }
                }
                else {
                    $data{$page} = '';
                }
            }
            else {
                $data{$page} = '';
            }
        }
        return keys %data;
    }
}    # This ends the bare block around get_all_pages_in_category()

=head2 linksearch($link[,$ns[,$protocol[,$options]]])

Runs a linksearch on the specified link and returns an array containing anonymous hashes with keys 'url' for the outbound URL, and 'title' for the page the link is on. $ns is a namespace number to search (pass an arrayref to search in multiple namespaces). You can search by $protocol (http is default). The optional $options hashref is fully documented in MediaWiki::API: Set `max` to limit the number of queries performed. Set `hook` to a subroutine reference to use a callback hook for incremental processing.

Set max in $options to get more than one query's worth of results:

    my $options = { max => 10, }; # I only want some results
    my @links = $bot->linksearch("slashdot.org", 1, undef, $options);
    foreach my $hash (@links) {
        my $url = $hash->{'url'};
        my $page = $hash->{'title'};
        print "$page: $url\n";
    }

You can also specify a callback function in $options:

    my $options = { hook => \&mysub, }; # I want to do incremental processing
    $bot->linksearch("slashdot.org", 1, undef, $options);
    sub mysub {
        my ($res) = @_;
        foreach my $hashref (@$res) {
            my $url  = $hashref->{'url'};
            my $page = $hashref->{'title'};
            print "$page: $url\n";
        }
    }

=cut

sub linksearch {
    my $self    = shift;
    my $link    = shift;
    my $ns      = shift;
    my $prot    = shift;
    my $options = shift;

    $ns = join('|', @$ns) if (ref $ns eq 'ARRAY');

    my $hash = {
        action      => 'query',
        list        => 'exturlusage',
        euprop      => 'url|title',
        euquery     => $link,
        eunamespace => $ns,
        euprotocol  => $prot,
        eulimit     => 'max',
    };
    $options->{'max'} = 1 unless $options->{'max'};

    my $res = $self->{api}->list($hash, $options);
    return $self->_handle_api_error() unless $res;
    return 1 if (!ref $res);    # When using a callback hook, this won't be a reference
    my @links;
    foreach my $hashref (@$res) {
        my $url  = $hashref->{'url'};
        my $page = $hashref->{'title'};
        push(@links, { 'url' => $url, 'title' => $page });
    }
    return @links;
}

=head2 purge_page($pagename)

Purges the server cache of the specified page. Pass an array reference to purge multiple pages. Returns true on success; false on failure. If you really care, a true return value is the number of pages successfully purged. You could check that it is the same as the number you wanted to purge.- maybe some pages don't exist, or you passed invalid titles, or you aren't allowed to purge the cache:

    my @to_purge = ('Main Page', 'A', 'B', 'C', 'Very unlikely to exist');
    my $size = scalar @to_purge;

    print "all-at-once:\n";
    my $success = $bot->purge_page(\@to_purge);

    if ($success == $size) {
        print "@to_purge: OK ($success/$size)\n";
    }
    else {
        my $missed = @to_purge - $success;
        print "We couldn't purge $missed pages (list was: "
            . join(', ', @to_purge)
            . ")\n";
    }

    # OR
    print "\n\none-at-a-time:\n";
    foreach my $page (@to_purge) {
        my $ok = $bot->purge_page($page);
        print "$page: $ok\n";
    }

=cut

sub purge_page {
    my $self = shift;
    my $page = shift;

    my $hash;
    if (ref $page eq 'ARRAY') {             # If it is an array reference...
        $hash = {
            action => 'purge',
            titles => join('|', @$page),    # dereference it and purge all those titles
        };
    }
    else {                                  # Just one page
        $hash = {
            action => 'purge',
            titles => $page,
        };
    }

    my $res = $self->{api}->api($hash);
    return $self->_handle_api_error() unless $res;
    my $success = 0;
    foreach my $hashref (@{ $res->{'purge'} }) {
        $success++ if exists $hashref->{'purged'};
    }
    return $success;
}

=head2 get_namespace_names()

get_namespace_names returns a hash linking the namespace id, such as 1, to its named equivalent, such as "Talk".

=cut

sub get_namespace_names {
    my $self = shift;
    my %return;
    my $res = $self->{api}->api({
            action => 'query',
            meta   => 'siteinfo',
            siprop => 'namespaces'
    });
    return $self->_handle_api_error() unless $res;
    foreach my $id (keys %{ $res->{query}->{namespaces} }) {
        $return{$id} = $res->{query}->{namespaces}->{$id}->{'*'};
    }
    if ($return{1} or $_[0] > 1) {
        return %return;
    }
    else {
        return $self->get_namespace_names($_[0] + 1);
    }
}

=head2 image_usage($image[,$ns[,$filter,[$options]]])

Gets a list of pages which include a certain image. Additional parameters are the namespace number to fetch results from (or an arrayref of multiple namespace numbers); $filter is 'all', 'redirect' (to return only redirects), or 'nonredirects' (to return no redirects). $options is a hashref as described in the section for linksearch().

    my @pages = $bot->image_usage("File:Albert Einstein Head.jpg");

or, make use of the options hashref to do incremental processing:

    $bot->image_usage("File:Albert Einstein Head.jpg", undef, undef, {hook=>\&mysub, max=>5});
    sub mysub {
        my $res = shift;
        foreach my $page (@$res) {
            my $title = $page->{'title'};
            print "$title\n";
        }
    }

=cut

sub image_usage {
    my $self    = shift;
    my $image   = shift;
    my $ns      = shift;
    my $filter  = shift;
    my $options = shift;

    if ($image !~ m/^File:|Image:/) {
        my $ns_data = $self->_get_ns_data();
        my $image_ns_name = $ns_data->{'6'};
        if ($image !~ m/^\Q$image_ns_name\E:/) {
            $image = "$image_ns_name:$image";
        }
    }

    $options->{'max'} = 1 unless defined($options->{'max'});
    delete($options->{'max'}) if $options->{'max'} == 0;

    $ns = join('|', @$ns) if (ref $ns eq 'ARRAY');

    my $hash = {
        action          => 'query',
        list            => 'imageusage',
        iutitle         => $image,
        iunamespace     => $ns,
        iulimit         => 'max',
    };
    if (defined($filter) and $filter =~ m/(all|redirects|nonredirects)/) {
        $hash->{'iufilterredir'} = $1;
    }
    my $res = $self->{api}->list($hash, $options);
    return $self->_handle_api_error() unless $res;
    return 1 if (!ref $res);    # When using a callback hook, this won't be a reference
    my @pages;
    foreach my $hashref (@$res) {
        my $title = $hashref->{'title'};
        push(@pages, $title);
    }

    return @pages;
}

=head2 links_to_image($image)

A backward-compatible call to image_usage(). You can provide only the image name.

=cut

sub links_to_image {
    my $self = shift;
    return $self->image_usage($_[0]);
}

=head2 is_blocked($user)

Checks if a user is currently blocked.

=cut

sub is_blocked {
    my $self = shift;
    my $user = shift;

    # http://en.wikipedia.org/w/api.php?action=query&meta=blocks&bkusers=$user&bklimit=1&bkprop=id
    my $hash = {
        action  => 'query',
        list    => 'blocks',
        bkusers => $user,
        bklimit => 1,
        bkprop  => 'id',
    };
    my $res = $self->{api}->api($hash);
    return $self->_handle_api_error() unless $res;

    my $number = scalar @{ $res->{query}->{"blocks"} };    # The number of blocks returned
    if ($number == 1) {
        return 1;
    }
    elsif ($number == 0) {
        return 0;
    }
    else {
        return; # UNPOSSIBLE!
    }
}

=head2 test_blocked($user)

Retained for backwards compatibility. Use is_blocked($user) for clarity.

=cut

sub test_blocked { # For backwards-compatibility
    return (is_blocked(@_));
}

=head2 test_image_exists($page)

Checks if an image exists at $page. 0 means no, 1 means yes, local, 2
means on commons, 3 means doesn't exist but there is text on the page.
If you pass in an arrayref of images, you'll get out an arrayref of
results.

    my $exists = $bot->test_image_exists('File:Albert Einstein Head.jpg');
    if ($exists == 0) {
        print "Doesn't exist\n";
    }
    elsif ($exists == 1) {
        print "Exists locally\n";
    }
    elsif ($exists == 2) {
        print "Exists on Commons\n";
    }

=cut

sub test_image_exists {
    my $self  = shift;
    my $image = shift;

    my $multi = 0;
    if (ref $image eq 'ARRAY') {
        $image = join('|', @$image);
        $multi = 1; # so we know whether to return a hash or a single scalar
    }

    my $res = $self->{api}->api({
        action  => 'query',
        titles  => $image,
        iilimit => 1,
        prop    => 'imageinfo'
    });
    return $self->_handle_api_error() unless $res;

    my @return;
    # use Data::Dumper; print STDERR Dumper($res) and die;
    foreach my $id (keys %{ $res->{query}->{pages} }) {
        my $title = $res->{query}->{pages}->{$id}->{title};
        if ($res->{query}->{pages}->{$id}->{imagerepository} eq 'shared') {
            if ($multi) {
                unshift @return, 2;
            }
            else {
                return 2;
            }
        }
        elsif (exists($res->{query}->{pages}->{$id}->{missing})) {
            if ($multi) {
                unshift @return, 0;
            }
            else {
                return 0;
            }
        }
        elsif ($res->{query}->{pages}->{$id}->{imagerepository} eq '') {
            if ($multi) {
                unshift @return, 3;
            }
            else {
                return 3;
            }
        }
        elsif ($res->{query}->{pages}->{$id}->{imagerepository} eq 'local') {
            if ($multi) {
                unshift @return, 1;
            }
            else {
                return 1;
            }
        }
    }

    # use Data::Dumper; print STDERR Dumper(\@return) and die;
    return \@return;
}

=head2 get_pages_in_namespace($namespace_id, $page_limit)

Returns an array containing the names of all pages in the specified namespace. The $namespace_id must be a number, not a namespace name. Setting $page_limit is optional. If $page_limit is over 500, it will be rounded up to the next multiple of 500.

=cut

sub get_pages_in_namespace {
    my $self      = shift;
    my $namespace = shift;
    my $limit     = shift || 'max';
    my $options   = shift;

    my $hash = {
        action      => 'query',
        list        => 'allpages',
        apnamespace => $namespace,
        aplimit     => $limit,
    };
    $options->{'max'} = 1 unless $options->{'max'};

    my $res = $self->{api}->list($hash, $options);
    return $self->_handle_api_error() unless $res;
    return 1 if (!ref $res);    # Not a ref when using callback
    my @return;
    foreach (@{$res}) {
        push @return, $_->{title};
    }
    return @return;
}

=head2 count_contributions($user)

Uses the API to count $user's contributions.

=cut

sub count_contributions {
    my $self     = shift;
    my $username = shift;
    $username =~ s/User://i;    # Strip namespace

    my $res = $self->{api}->list({
            action  => 'query',
            list    => 'users',
            ususers => $username,
            usprop  => 'editcount'
        },
        { max => 1 });
    return $self->_handle_api_error() unless $res;
    return ${$res}[0]->{'editcount'};
}

=head2 last_active($user)

Returns the last active time of $user in YYYY-MM-DDTHH:MM:SSZ

=cut

sub last_active {
    my $self     = shift;
    my $username = shift;
    unless ($username =~ /User:/i) { $username = "User:" . $username; }
    my $res = $self->{api}->list({
            action  => 'query',
            list    => 'usercontribs',
            ucuser  => $username,
            uclimit => 1
        },
        { max => 1 });
    return $self->_handle_api_error() unless $res;
    return ${$res}[0]->{'timestamp'};
}

=head2 recent_edit_to_page($page)

Returns timestamp and username for most recent (top) edit to $page.

=cut

sub recent_edit_to_page {
    my $self = shift;
    my $page = shift;
    my $res  = $self->{api}->api({
            action  => 'query',
            prop    => 'revisions',
            titles  => $page,
            rvlimit => 1
        },
        { max => 1 });
    return $self->_handle_api_error() unless $res;
    my ($id, $data) = %{ $res->{query}->{pages} };
    return $data->{revisions}[0]->{timestamp};
}

=head2 get_users($page, $limit, $revision, $direction)

Gets the most recent editors to $page, up to $limit, starting from $revision and goint in $direction.

=cut

sub get_users {
    my $self      = shift;
    my $pagename  = shift;
    my $limit     = shift || 'max';
    my $rvstartid = shift;
    my $direction = shift;

    my @return;
    my @revisions;

    if ($limit > 50) {
        $self->{errstr} = "Error requesting history for $pagename: Limit may not be set to values above 50";
        carp $self->{errstr};
        return;
    }
    my $hash = {
        action  => 'query',
        prop    => 'revisions',
        titles  => $pagename,
        rvprop  => 'ids|timestamp|user|comment',
        rvlimit => $limit,
    };
    $hash->{rvstartid} = $rvstartid if ($rvstartid);
    $hash->{rvdir}     = $direction if ($direction);

    my $res = $self->{api}->api($hash);
    return $self->_handle_api_error() unless $res;

    my ($id) = keys %{ $res->{query}->{pages} };
    my $array = $res->{query}->{pages}->{$id}->{revisions};
    foreach (@{$array}) {
        push @return, $_->{user};
    }

    return @return;
}

=head2 was_blocked($user)

Returns 1 if $user has ever been blocked.

=cut

sub was_blocked {
    my $self = shift;
    my $user = shift;
    $user =~ s/User://i;    # Strip User: prefix, if present

    # http://en.wikipedia.org/w/api.php?action=query&list=logevents&letype=block&letitle=User:127.0.0.1&lelimit=1&leprop=ids
    my $hash = {
        action  => 'query',
        list    => 'logevents',
        letype  => 'block',
        letitle => "User:$user",    # Ensure the User: prefix is there!
        lelimit => 1,
        leprop  => 'ids',
    };

    my $res = $self->{api}->api($hash);
    return $self->_handle_api_error() unless $res;

    my $number = scalar @{ $res->{'query'}->{'logevents'} };    # The number of blocks returned
    if ($number == 1) {
        return 1;
    }
    elsif ($number == 0) {
        return 0;
    }
    else {
        return; # UNPOSSIBLE!
    }
}

=head2 test_block_hist($user)

Retained for backwards compatibility. Use was_blocked($user) for clarity.

=cut

sub test_block_hist { # Backwards compatibility
    return (was_blocked(@_));
}

=head2 expandtemplates($page[, $text])

Expands templates on $page, using $text if provided, otherwise loading the page text automatically.

=cut

sub expandtemplates {
    my $self = shift;
    my $page = shift;
    my $text = shift;

    unless ($text) {
        $text = $self->get_text($page);
    }

    my $hash = {
        action => 'expandtemplates',
        title  => $page,
        text   => $text,
    };
    my $res = $self->{api}->api($hash);
    return $self->_handle_api_error() unless $res;
    my $expanded = $res->{'expandtemplates'}->{'*'};

    return $expanded;
}

=head2 get_allusers($limit, $group, $opts)

Returns an array of all users. Default limit is 500. Optionally specify a group to list that group only. The last optional parameter is an options hashref, as detailed elsewhere.

=cut

sub get_allusers {
    my $self   = shift;
    my $limit  = shift || 'max';
    my $group  = shift;
    my $opts   = shift;

    my $hash = {
            action  => 'query',
            list    => 'allusers',
            aulimit => $limit,
    };
    $hash->{augroup} = $group if defined $group;
    $opts->{max} = 1 unless exists $opts->{max};
    delete $opts->{max} if exists $opts->{max} and $opts->{max} == 0;
    my $res = $self->{api}->list($hash, $opts);
    return $self->_handle_api_error() unless $res;
    return 1 if (!ref $res);    # Not a ref when using callback

    my @return;
    for my $ref (@{ $res }) {
        push @return, $ref->{name};
    }
    return @return;
}

=head2 db_to_domain($wiki)

Converts a wiki/database name (enwiki) to the domain name (en.wikipedia.org).

    my @wikis = ("enwiki", "kowiki", "bat-smgwiki", "nonexistent");
    foreach my $wiki (@wikis) {
        my $domain = $bot->db_to_domain($wiki);
        next if !defined($domain);
        print "$wiki: $domain\n";
    }

You can pass an arrayref to do bulk lookup:

    my @wikis = ("enwiki", "kowiki", "bat-smgwiki", "nonexistent");
    my $domains = $bot->db_to_domain(\@wikis);
    foreach my $domain (@$domains) {
        next if !defined($domain);
        print "$domain\n";
    }

=cut

sub db_to_domain {
    my $self = shift;
    my $wiki = shift;

    if (!$self->{sitematrix}) {
        $self->_get_sitematrix();
    }

    if (ref $wiki eq 'ARRAY') {
        my @return;
        foreach my $w (@$wiki) {
            $wiki =~ s/_p$//;    # Strip off a _p suffix, if present
            my $domain = $self->{'sitematrix'}->{$w} || undef;
            push(@return, $domain);
        }
        return \@return;
    }
    else {
        $wiki =~ s/_p$//;        # Strip off a _p suffix, if present
        my $domain = $self->{'sitematrix'}->{$wiki} || undef;
        return $domain;
    }
}

=head2 domain_to_db($wiki)

As you might expect, does the opposite of domain_to_db(): Converts a domain
name into a database/wiki name.

=cut

sub domain_to_db {
    my $self = shift;
    my $wiki = shift;

    if (!$self->{sitematrix}) {
        $self->_get_sitematrix();
    }

    if (ref $wiki eq 'ARRAY') {
        my @return;
        foreach my $w (@$wiki) {
            my $db = $self->{'sitematrix'}->{$w} || undef;
            push(@return, $db);
        }
        return \@return;
    }
    else {
        my $db = $self->{'sitematrix'}->{$wiki} || undef;
        return $db;
    }
}

=head2 diff($options_hashref)

This allows retrieval of a diff from the API. The return is a scalar containing the HTML table of the diff. Options are as follows:

=over 4

=item *
title is the title to use. Provide I<either> this or revid.

=item *
revid is any revid to diff from. If you also specified title, only title will be honoured.

=item *
oldid is an identifier to diff to. This can be a revid, or the special values 'cur', 'prev' or 'next'

=back

=cut

sub diff {
    my $self = shift;
    my $title;
    my $revid;
    my $oldid;

    if (ref $_[0] eq 'HASH') {
        $title = $_[0]->{'title'};
        $revid = $_[0]->{'revid'};
        $oldid = $_[0]->{'oldid'};
    }
    else {
        $title = shift;
        $revid = shift;
        $oldid = shift;
    }

    my $hash = {
        action   => 'query',
        prop     => 'revisions',
        rvdiffto => $oldid,
    };
    if ($title) {
        $hash->{'titles'}  = $title;
        $hash->{'rvlimit'} = 1;
    }
    elsif ($revid) {
        $hash->{'revids'} = $revid;
    }

    my $res = $self->{api}->api($hash);
    return $self->_handle_api_error() unless $res;
    my @revids = keys %{ $res->{'query'}->{'pages'} };
    my $diff   = $res->{'query'}->{'pages'}->{ $revids[0] }->{'revisions'}->[0]->{'diff'}->{'*'};

    return $diff;
}

=head2 prefixindex($prefix[,$filter[,$ns[,$options]]])

This returns an array of hashrefs containing page titles that start with the given $prefix. $filter is one of 'all', 'redirects', or 'nonredirects'; $ns is a single namespace number (unlike linksearch etc, which can accept an arrayref of numbers). $options is a hashref as described in the section on linksearch() or in MediaWiki::API. The hashref has keys 'title' and 'redirect' (present if the page is a redirect, not present otherwise).

    my @prefix_pages = $bot->prefixindex("User:Mike.lifeguard");
    # Or, the more efficient equivalent
    my @prefix_pages = $bot->prefixindex("Mike.lifeguard", 2);
    foreach my $hashref (@pages) {
        my $title = $hashref->{'title'};
        if $hashref->{'redirect'} {
            print "$title is a redirect\n";
        }
        else {
            print "$title\n is not a redirect\n";
        }
    }

=cut

sub prefixindex {
    my $self    = shift;
    my $prefix  = shift;
    my $ns      = shift;
    my $filter  = shift;
    my $options = shift;

    if (defined($filter) and $filter =~ m/(all|redirects|nonredirects)/) {    # Verify
        $filter = $1;
    }

    if (!$ns && $prefix =~ m/:/) {
        print STDERR "Converted '$prefix' to..." if $self->{'debug'} > 1;
        my ($name) = split(/:/, $prefix, 2);
        my $ns_data = $self->_get_ns_data();
        $ns = $ns_data->{$name};
        $prefix =~ s/^$name://;
        warn "'$prefix' with a namespace filter $ns" if $self->{'debug'} > 1;
    }

    my $hash = {
        action   => 'query',
        list     => 'allpages',
        apprefix => $prefix,
        aplimit  => 'max',
    };
    $hash->{'apnamespace'}   = $ns     if $ns;
    $hash->{'apfilterredir'} = $filter if $filter;
    $options->{'max'} = 1 unless $options->{'max'};

    my $res = $self->{api}->list($hash, $options);

    return $self->_handle_api_error() unless $res;
    return 1 if (!ref $res);    # Not a ref when using callback hook
    my @pages;
    foreach my $hashref (@$res) {
        my $title    = $hashref->{'title'};
        my $redirect = defined($hashref->{'redirect'});
        push @pages, { title => $title, redirect => $redirect };
    }

    return @pages;
}

=head2 search($search_term[,$ns[,$options_hashref]])

This is a simple search for your $search_term in page text. $ns is a namespace number to search in, or an arrayref of numbers (default is main namespace). $options_hashref is a hashref as described in MediaWiki::API or the section on linksearch(). It returns an array of page titles matching.

    my @pages = $bot->search("Mike.lifeguard", 2);
    print "@pages\n";

Or, use a callback for incremental processing:

    my @pages = $bot->search("Mike.lifeguard", 2, { hook => \&mysub });
    sub mysub {
        my ($res) = @_;
        foreach my $hashref (@$res) {
            my $page = $hashref->{'title'};
            print "$page\n";
        }
    }

=cut

sub search {
    my $self    = shift;
    my $term    = shift;
    my $ns      = shift || 0;
    my $options = shift;

    if (ref $ns eq 'ARRAY') {    # Accept a hashref
        $ns = join('|', @$ns);
    }

    my $hash = {
        action   => 'query',
        list     => 'search',
        srsearch => $term,
        srwhat   => 'text',
        srlimit  => 'max',

        #srinfo      => 'totalhits',
        srprop      => 'size',
        srredirects => 0,
    };
    $options->{'max'} = 1 unless $options->{'max'};

    my $res = $self->{api}->list($hash, $options);
    return $self->_handle_api_error() unless $res;
    return 1 if (!ref $res);    # Not a ref when used with callback
    my @pages;
    foreach my $result (@$res) {
        my $title = $result->{'title'};
        push @pages, $title;
    }

    return @pages;
}

=head2 get_log($data, $options)

This fetches log entries, and returns results as an array of hashes. The options are as follows:

=over 4

=item *
type is the log type (block, delete...)

=item *
user is the user who I<performed> the action. Do not include the User: prefix

=item *
target is the target of the action. Where an action was performed to a page, it is the page title. Where an action was performed to a user, it is User:$username.

=back

    my $log = $bot->get_log({
            type => 'block',
            user => 'User:Mike.lifeguard',
        });
    foreach my $entry (@$log) {
        my $user = $entry->{'title'};
        print "$user\n";
    }

    $bot->get_log({
            type => 'block',
            user => 'User:Mike.lifeguard',
        },
        { hook => \&mysub, max => 10 }
    );
    sub mysub {
        my ($res) = @_;
        foreach my $hashref (@$res) {
            my $title = $hashref->{'title'};
            print "$title\n";
        }
    }

=cut

sub get_log {
    my $self    = shift;
    my $data    = shift;
    my $options = shift;

    my $log_type = $data->{'type'};
    my $user     = $data->{'user'};
    my $target   = $data->{'target'};

    my $ns_data      = $self->_get_ns_data();
    my $user_ns_name = $ns_data->{'2'};
    $user =~ s/^$user_ns_name://;

    my $hash = {
        action  => 'query',
        list    => 'logevents',
        lelimit => 'max',
    };
    $hash->{'letype'}  = $log_type if $log_type;
    $hash->{'leuser'}  = $user     if $user;
    $hash->{'letitle'} = $target   if $target;
    $options->{'max'} = 1 unless $options->{'max'};

    my $res = $self->{api}->list($hash, $options);
    return $self->_handle_api_error() unless $res;
    return 1 if (!ref $res);    # Not a ref when using callback

    return $res;
}

=head2 is_g_blocked($ip)

Returns what IP/range block I<currently in place> affects the IP/range. The return is a scalar of an IP/range if found (evaluates to true in boolean context); undef otherwise (evaluates false in boolean context). Pass in a single IP or CIDR range.

=cut

sub is_g_blocked {
    my $self = shift;
    my $ip   = shift;

    # http://en.wikipedia.org/w/api.php?action=query&list=globalblocks&bglimit=1&bgprop=address&bgip=127.0.0.1
    my $res = $self->{api}->api({
            action  => 'query',
            list    => 'globalblocks',
            bglimit => 1,
            bgprop  => 'address',
            bgip    => $ip,              # So handy! It searches for blocks affecting this IP or IP range, including rangeblocks! Can't get that from UI.
    });
    return $self->_handle_api_error() unless $res;
    return 0 unless ($res->{'query'}->{'globalblocks'}->[0]);

    return $res->{'query'}->{'globalblocks'}->[0]->{'address'};
}

=head2 was_g_blocked($ip)

Returns whether an IP/range was ever globally blocked. You should probably call this method only when your bot is operating on Meta.

=cut

sub was_g_blocked {
    my $self = shift;
    my $ip   = shift;
    $ip =~ s/User://i; # Strip User: prefix, if present

    # This query should always go to Meta
    unless ($self->{api}->{config}->{api_url} =~
        m,
            http://meta.wikimedia.org/w/api.php
                |
            https://secure.wikimedia.org/wikipedia/meta/w/api.php
        ,x # /x flag is pretty awesome :)
        ) {
        carp "GlobalBlocking queries should probably be sent to Meta; it doesn't look like you're doing so" if $self->{'debug'};
    }

    # http://meta.wikimedia.org/w/api.php?action=query&list=logevents&letype=gblblock&letitle=User:127.0.0.1&lelimit=1&leprop=ids
    my $hash = {
        action  => 'query',
        list    => 'logevents',
        letype  => 'gblblock',
        letitle => "User:$ip",    # Ensure the User: prefix is there!
        lelimit => 1,
        leprop  => 'ids',
    };
    my $res = $self->{api}->api($hash);

    return $self->_handle_api_error() unless $res;
    my $number = scalar @{ $res->{'query'}->{'logevents'} };    # The number of blocks returned

    if ($number == 1) {
        return 1;
    }
    elsif ($number == 0) {
        return 0;
    }
    else {
        return; # UNPOSSIBLE!
    }
}

=head2 was_locked($user)

Returns whether a user was ever locked.

=cut

sub was_locked {
    my $self = shift;
    my $user = shift;

    # This query should always go to Meta
    unless (
        $self->{api}->{config}->{api_url} =~ m,
            http://meta.wikimedia.org/w/api.php
                |
            https://secure.wikimedia.org/wikipedia/meta/w/api.php
        ,x    # /x flag is pretty awesome :)
        )
    {
        carp "CentralAuth queries should probably be sent to Meta; it doesn't look like you're doing so" if $self->{'debug'};
    }

    $user =~ s/^User://i;
    $user =~ s/\@global$//i;
    my $res = $self->{'api'}->api({
            action  => 'query',
            list    => 'logevents',
            letype  => 'globalauth',
            letitle => "User:$user\@global",
            lelimit => 1,
            leprop  => 'ids',
    });
    return $self->_handle_api_error() unless $res;
    my $number = scalar @{ $res->{'query'}->{'logevents'} };
    if ($number == 1) {
        return 1;
    }
    elsif ($number == 0) {
        return 0;
    }
    else {
        return;
    }
}

=head2 get_protection($page)

Returns data on page protection. If you care beyond true/false, information about page protection is returned as a array of up to two hashrefs. Each hashref has a type, level, and expiry. Levels are 'sysop' and 'autoconfirmed'; types are 'move' and 'edit'; expiry is a timestamp. Additionally, the key 'cascade' will exist if cascading protection is used.

    my $page = "Main Page";
    $bot->edit({
        page    => $page,
        text    => rand(),
        summary => 'test',
    }) unless $bot->get_protection($page);

You can also pass an arrayref of page titles to do bulk queries:

    my @pages = ("Main Page", "User:Mike.lifeguard", "Project:Sandbox");
    my $answer = $bot->get_protection(\@pages);
    foreach my $title (keys %$answer) {
        my $protected = $answer->{$title};
        print "$title is protected\n" if $protected;
        print "$title is unprotected\n" unless $protected;
    }

=cut

sub get_protection {
    my $self = shift;
    my $page = shift;
    if (ref $page eq 'ARRAY') {
        $page = join('|', @$page);
    }

    my $hash = {
        action => 'query',
        titles => $page,
        prop   => 'info',
        inprop => 'protection',
    };
    my $res = $self->{api}->api($hash);
    return $self->_handle_api_error() unless $res;

    my $data = $res->{'query'}->{'pages'};

    my $out_data;
    foreach my $item (keys %$data) {
        my $title      = $data->{$item}->{'title'};
        my $protection = $data->{$item}->{'protection'};
        if (@$protection == 0) {
            $protection = undef;
        }
        $out_data->{$title} = $protection;
    }

    if (scalar keys %$out_data == 1) {
        return $out_data->{$page};
    }
    else {
        return $out_data;
    }
}

=head2 is_protected($page)

This is a synonym for get_protection(), which should be used in preference.

=cut

sub is_protected {
    my $self = shift;
    return $self->get_protection(@_);
}

=head2 patrol($rcid)

Marks a page or revision identified by the rcid as patrolled. To mark several rcids as patrolled, you may pass an arrayref.

=cut

sub patrol {
    my $self = shift;
    my $rcid = shift;

    if (ref $rcid eq 'ARRAY') {
        my @return;
        foreach my $id (@$rcid) {
            my $res = $self->patrol($id);
            push(@return, $res);
        }
        return @return;
    }
    else {
        my ($token) = $self->_get_edittoken();
        my $res = $self->{api}->api({
                action => 'patrol',
                rcid   => $rcid,
                token  => $token,
        });
        return $self->_handle_api_error() unless $res;
        return $res;
    }
}

=head2 email($user, $subject, $body)

This allows you to send emails through the wiki. All 3 of $user (without the User: prefix), $subject and $body are required. If $user is an arrayref, this will send the same email (subject and body) to all users.

=cut

sub email {
    my $self    = shift;
    my $user    = shift;
    my $subject = shift;
    my $body    = shift;

    if (ref $user eq 'ARRAY') {
        my @return;
        foreach my $target (@$user) {
            my $res = $self->email($target, $subject, $body);
            push(@return, $res);
        }
        return @return;
    }

    $user =~ s/^User://;
    if ($user =~ m/:/) {
        my $user_ns_name = $self->_get_ns_data()->{2};
        $user =~ s/^$user_ns_name://;
    }

    my ($token) = $self->_get_edittoken();
    my $res = $self->{api}->api({
        action  => 'emailuser',
        target  => $user,
        subject => $subject,
        text    => $body,
        token   => $token,
    });
    return $self->_handle_api_error() unless $res;
    return $res;
}

=head2 top_edits($user[,$options])

Returns an array of the page titles where the user is the latest editor.

    my @pages = $bot->top_edits("Mike.lifeguard", {max => 5});
    foreach my $page (@pages) {
        $bot->rollback($page, "Mike.lifeguard");
    }

Note that accessing the data with a callback happens B<before> filtering
the top edits is done. For that reason, you should use C<contributions()>
if you need to use a callback. If you use a callback with C<top_edits()>,
you B<will not> get top edits returned. It is safe to use a callback if
you I<check> that it is a top edit:

    $bot->top_edits("Mike.lifeguard", { hook => \&rv });
    sub rv {
        my $data = shift;
        foreach my $page (@$data) {
            if (exists($page->{'top'})) {
                $bot->rollback($page->{'title'}, "Mike.lifeguard");
            }
        }
    }

=cut

sub top_edits {
    my $self    = shift;
    my $user    = shift;
    my $options = shift;

    $user =~ s/^User://;

    $options->{'max'} = 1 unless defined($options->{'max'});
    delete($options->{'max'}) if $options->{'max'} == 0;

    my $res = $self->{'api'}->list({
        action  => 'query',
        list    => 'usercontribs',
        ucuser  => $user,
        ucprop  => 'title|flags',
        uclimit => 'max',
    }, $options);
    return _handle_api_error() unless $res;
    return 1 if (!ref $res);    # Not a ref when using callback

    my @titles;
    foreach my $page (@$res) {
        push @titles, $page->{'title'} if exists($page->{'top'});
    }

    return @titles;
}

=head2 contributions($user, $ns, $options)

Returns an array of hashrefs of data for the user's contributions. $ns can be an
arrayref of namespace numbers.

=cut

sub contributions {
    my $self = shift;
    my $user = shift;
    my $ns   = shift;
    my $opts = shift;

    $user =~ s/^User://;

    $ns = join('|', @$ns) if (ref $ns eq 'ARRAY');

    $opts->{'max'} = 1 unless defined($opts->{'max'});
    delete($opts->{'max'}) if $opts->{'max'} == 0;

    my $res = $self->{'api'}->list({
        action      => 'query',
        list        => 'usercontribs',
        ucuser      => $user,
        ucnamespace => $ns,
        ucprop      => 'ids|title|timestamp|comment|patrolled|flags',
        uclimit     => 'max',
    }, $opts);
    return _handle_api_error() unless $res;
    return 1 if (!ref $res);    # Not a ref when using callback

    return $res; # Can we make this more useful?
}

################
# Internal use #
################

sub _get_edittoken { # Actually returns ($edittoken, $basetimestamp, $starttimestamp)
    my $self = shift;
    my $page = shift || 'Main Page';
    my $type = shift || 'edit';

    my $res = $self->{api}->api({
        action  => 'query',
        titles  => $page,
        prop    => 'info|revisions',
        intoken => $type,
    }) or return $self->_handle_api_error();

    my ($id, $data) = %{ $res->{'query'}->{'pages'} };
    my $edittoken      = $data->{'edittoken'};
    my $tokentimestamp = $data->{'starttimestamp'};
    my $basetimestamp  = $data->{'revisions'}[0]->{'timestamp'};
    return ($edittoken, $basetimestamp, $tokentimestamp);
}

sub _handle_api_error {
    my $self = shift;
    carp 'Error code '
        . $self->{api}->{error}->{code}
        . ": "
        . $self->{api}->{error}->{details} if $self->{'debug'};
    $self->{error} = $self->{api}->{error};
    return;
}

sub _is_loggedin {
    my $self = shift;

    my $hash = {
        action => 'query',
        meta   => 'userinfo',
    };
    my $res = $self->{api}->api($hash);
    return $self->_handle_api_error() unless $res;
    my $is    = $res->{'query'}->{'userinfo'}->{'name'};
    my $ought = $self->{username};
    warn "Testing if logged in: we are $is, and we should be $ought" if $self->{'debug'} > 1;
    return ($is eq $ought);
}

sub _do_autoconfig {
    my $self = shift;

    # http://en.wikipedia.org/w/api.php?action=query&meta=userinfo&uiprop=rights|groups
    my $hash = {
        action => 'query',
        meta   => 'userinfo',
        uiprop => 'rights|groups',
    };
    my $res = $self->{api}->api($hash);
    return $self->_handle_api_error() unless $res;

    my $is    = $res->{'query'}->{'userinfo'}->{'name'};
    my $ought = $self->{username};

    # Should we try to recover by logging in again? croak?
    carp "We're logged in as $is but we should be logged in as $ought" if ($is ne $ought);

    my @rights            = @{ $res->{'query'}->{'userinfo'}->{'rights'} };
    my $has_bot           = 0;
    my $has_apihighlimits = 0;
    my $default_assert    = 'user';                                           # At a *minimum*, the bot should be logged in.
    foreach my $right (@rights) {
        if ($right eq 'bot') {
            $has_bot        = 1;
            $default_assert = 'bot';
        }
        elsif ($right eq 'apihighlimits') {
            $has_apihighlimits = 1;
        }
    }

    my @groups   = @{ $res->{'query'}->{'userinfo'}->{'groups'} };
    my $is_sysop = 0;
    foreach my $group (@groups) {
        if ($group eq 'sysop') {
            $is_sysop = 1;
        }
    }

    unless ($has_bot && !$is_sysop) {
        warn "$is doesn't have a bot flag; edits will be visible in RecentChanges" if $self->{'debug'} > 1;
    }
    $self->set_highlimits($has_apihighlimits);
    $self->{'assert'} = $default_assert unless $self->{'assert'};

    return 1;
}

sub _get_sitematrix {
    my $self = shift;

    my $res = $self->{api}->api({ action => 'sitematrix' });
    return $self->_handle_api_error() unless $res;
    my %sitematrix = %{ $res->{'sitematrix'} };

#    use Data::Dumper;
#    print STDERR Dumper(\%sitematrix) and die;
    # This hash is a monstrosity (see http://sprunge.us/dfBD?pl), and needs
    # lots of post-processing to have a sane data structure :\
    my %by_db;
    SECTION: foreach my $hashref (%sitematrix) {
        if (ref $hashref ne 'HASH') {    # Yes, there are non-hashrefs in here, wtf?!
            if ($hashref eq 'specials') {
                SPECIAL: foreach my $special (@{ $sitematrix{'specials'} }) {
                    next SPECIAL
                        if (exists($special->{'private'})
                        or exists($special->{'fishbowl'}));

                    my $db     = $special->{'code'};
                    my $domain = $special->{'url'};
                    $domain =~ s,^http://,,;

                    $by_db{$db}     = $domain;
                }
            }
            next SECTION;
        }

        my $lang = $hashref->{'code'};

        WIKI: foreach my $wiki_ref ($hashref->{'site'}) {
            WIKI2: foreach my $wiki_ref2 (@$wiki_ref) {
                my $family = $wiki_ref2->{'code'};
                my $domain = $wiki_ref2->{'url'};
                $domain =~ s,^http://,,;

                my $db = $lang . $family;    # Is simple concatenation /always/ correct?

                $by_db{$db}     = $domain;
            }
        }
    }

    # Now filter out closed wikis
    my $response = $self->{api}->{ua}->get('http://noc.wikimedia.org/conf/closed.dblist');
    if ($response->is_success()) {
        my @closed_list = split(/\n/, $response->decoded_content);
        CLOSED: foreach my $closed (@closed_list) {
            delete($by_db{$closed});
        }
    }

    # Now merge in the reverse, so you can look up by domain as well as db
    my %by_domain;
    while (my ($key, $value) = each %by_db) {
        $by_domain{$value} = $key;
    }
    %by_db = (%by_db, %by_domain);

    # This could be saved to disk with Storable. Next time you call this
    # method, if mtime is less than, say, 14d, you could load it from
    # disk instead of over network.
    $self->{'sitematrix'} = \%by_db;
#    use Data::Dumper;
#    print STDERR Dumper($self->{'sitematrix'}) and die;
    return $self->{'sitematrix'};
}

sub _get_ns_data {
    my $self = shift;

    # If we have it already, return the cached data
    return $self->{'ns_data'} if exists($self->{'ns_data'});

    # If we haven't returned by now, we have to ask the API
    my %ns_data = $self->get_namespace_names();
    my %reverse = reverse %ns_data;
    %ns_data = (%ns_data, %reverse);
    $self->{'ns_data'} = \%ns_data;    # Save for later use

    return $self->{'ns_data'};
}

=head1 ERROR HANDLING

All functions will return undef in any handled error situation. Further error
data is stored in $bot->{'error'}->{'code'} and $bot->{'error'}->{'details'}.

=cut

1;

__END__