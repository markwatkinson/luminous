use Purple;
use XML::XPath;
use XML::XPath::XMLParser;
use POSIX;

use strict;
use warnings;

our %PLUGIN_INFO = (
    perl_api_version => 2,
    name => 'Twitter Status',
    version => '0.4.1',
    summary => 'Use a Twitter feed as your status message.',
    description => 'Use a Twitter feed as your status message.',
    author => 'Aaron D. Santos <aaronds109@gmail.com>, Kurt Nelson <kurt@thisisnotajoke.com>, '
              .'Patrick Tulskie <patricktulskie@gmail.com> and Ramkumar R. Aiyengar <andyetitmoves@gmail.com>',
    url => 'http://code.google.com/p/pidgin-twitterstatus/',

    load => 'plugin_load',
    unload => 'plugin_unload',
    prefs_info => 'prefs_info_cb'
);

#Begin Global Variables
my $pref_root = '/plugins/core/gtk-aaron_ds-twitterstatus';
my $log_category = 'twitterstatus';
my $user_agent = "pidgin-twitterstatus/$PLUGIN_INFO{version}";
my $source_agent = 'pidgintwitterstatus';

my $plugin_instance;
my $active_update_timer;
#End Global Variables

sub find_latest_tweet
{
        my @twitter_statuses = @_;
        my $out_status;

        my $pref_ignore_replies = Purple::Prefs::get_bool("$pref_root/ignore_replies");
        my $pref_filter_regex = Purple::Prefs::get_string("$pref_root/filter_regex");

        Purple::Debug::info($log_category, "Preferences: "
                            ."ignore_replies = $pref_ignore_replies, "
                            ."filter_regex = '$pref_filter_regex'\n");

        my $last_seen_id = Purple::Prefs::get_int("$pref_root/state/last_seen_id");
        my $last_seen_id_dirty;

        foreach my $this_status (@twitter_statuses) {

                my $this_status_id = $this_status->find('id')->string_value;
                if ($this_status_id > $last_seen_id) {
                        $last_seen_id = $this_status_id;
                        $last_seen_id_dirty = 1;
                }
                my $this_status_message = $this_status->find('text')->string_value;
                Purple::Debug::info($log_category, "Found twitter status $this_status_id: '$this_status_message'\n");

                my $emsg = do {
                        if ($this_status_id <= 0) { 'invalid status ID' }
                        elsif (length($this_status_message) <= 1) { 'too short' }
                        elsif ($pref_ignore_replies &&
                               ($this_status->find('in_reply_to_user_id')->string_value ||
                                $this_status->find('in_reply_to_status_id')->string_value ||
                                $this_status->find('in_reply_to_screen_name')->string_value)) { 'was a reply to someone' }
                        elsif ($pref_filter_regex && $this_status_message =~ m/$pref_filter_regex/) { 'matched the discard filter' }
                };
                if ($emsg) {
                        Purple::Debug::info($log_category, "Skipping status message: $emsg\n");
                } else {
                        $out_status = $this_status;
                        last;
                }
        }
        Purple::Prefs::set_int("$pref_root/state/last_seen_id", $last_seen_id) if $last_seen_id_dirty;
        return $out_status;
}

sub update_active_tweet
{
        my $tweet = shift;
        return unless $tweet;

        my $tweet_id = $tweet->find('id')->string_value;
        return if $tweet_id <= Purple::Prefs::get_int("$pref_root/state/last_updated_id");

        my $tweet_message = $tweet->find('text')->string_value;
        Purple::Prefs::set_int("$pref_root/state/last_updated_id", $tweet_id);
        Purple::Prefs::set_string("$pref_root/state/last_updated_text", $tweet_message);

        return $tweet_id;
}

sub get_savedstatuses_to_update {
        return map { (Purple::SavedStatus::find($_) ||
                      Purple::SavedStatus::new($_, 2)) }
          (split /\s*,\s*/, Purple::Prefs::get_string("$pref_root/savedstatuses_to_update"));
}

sub refresh_purple_status
{
        my $twitter_status = Purple::Prefs::get_string("$pref_root/state/last_updated_text");
        my $status_message = Purple::Prefs::get_string("$pref_root/status_template");
        $status_message =~ s/\%\%/\%/g;
        $status_message =~ s/\%s/$twitter_status/g;

        my $now_string = localtime;
        $status_message =~ s/\%t/$now_string/g;

        Purple::Debug::info($log_category, "Refreshing purple status to: $status_message\n");

        my @update_list = get_savedstatuses_to_update();
        my @dirty_list = (grep { $_->get_message() ne $status_message } @update_list);
        $_->set_message($status_message) foreach @dirty_list;

        my $cur_status = Purple::SavedStatus::get_default();
        my $cur_status_title = $cur_status->get_title();
        return unless $cur_status_title;
        $_->activate foreach (grep { $_->get_title() eq $cur_status_title } @dirty_list);
}

sub merge_twitter_response
{
        my ($twitter_response, $status_list_xpath) = @_;
        return unless $twitter_response;

        my $twitter_xml = XML::XPath->new(xml=>$twitter_response);
        my @twitter_statuses = $twitter_xml->find($status_list_xpath)->get_nodelist();

        my $tweet_id = update_active_tweet (find_latest_tweet(@twitter_statuses));
        refresh_purple_status();
        return $tweet_id;
}

sub fetch_url_cb
{
        my $twitter_response = shift;
        merge_twitter_response $twitter_response, '/statuses/status';
}

sub update_status
{
        my $timeout = shift;
        my $twitterusername = Purple::Prefs::get_string("$pref_root/twitterusername");
        if ($twitterusername =~ /[A-Za-z0-9_]+/) {
                my $api_root = Purple::Prefs::get_string("$pref_root/api_root");
                my (@url_params, $pref);
                $pref = Purple::Prefs::get_int("$pref_root/max_statuses_to_fetch");
                push @url_params, "count=$pref" if $pref > 0;
                $pref = Purple::Prefs::get_int("$pref_root/state/last_seen_id");
                push @url_params, "since_id=$pref" if $pref > 0;

                my $twitterurl = "$api_root/statuses/user_timeline/$twitterusername.xml?".(join '&', @url_params);

                Purple::Util::fetch_url($plugin_instance, $twitterurl, 1, $user_agent, 1, 'fetch_url_cb');
        } else {
                Purple::Debug::error($log_category, "Blank or invalid username: '$twitterusername'\n");
        }
}

sub schedule_status_update
{
        my $delay = ((shift) || Purple::Prefs::get_int("$pref_root/poll_interval"));

        # If there's a timer already ticking, remove that first
        if ($active_update_timer) {
                Purple::Debug::info($log_category, "Cancelling current scheduled status update\n");
                Purple::timeout_remove($active_update_timer);
                undef $active_update_timer;
        }

        $active_update_timer = Purple::timeout_add($plugin_instance, $delay, \&timeout_cb);
        Purple::Debug::info($log_category, "Scheduling next status update in $delay seconds\n");
}

sub timeout_cb
{
        undef $active_update_timer;
        Purple::Debug::info($log_category, "Starting the sequence.  Pidgin's timer expired.\n");
        my $poll_interval = Purple::Prefs::get_int("$pref_root/poll_interval");
        update_status $poll_interval;
        schedule_status_update $poll_interval;
}

sub send_tweet
{
        my $status = shift;
        return unless $status;
        return unless Purple::Prefs::get_bool("$pref_root/sendstatus");

        Purple::Debug::info($log_category, "Tweeting back: $status\n");
        $status = Purple::Util::url_encode($status);

        my $pref_username = Purple::Prefs::get_string("$pref_root/twitterusername");
        my $pref_password = Purple::Prefs::get_string("$pref_root/twitterpassword");
        my $api_root = Purple::Prefs::get_string("$pref_root/api_root");

        my $pid = open (KID_TO_READ, '-|');
        unless ($pid) { # child
                exec ('curl', '--user', "$pref_username:$pref_password",
                      '--data', "status=$status", '--data', "source=$source_agent",
                      "$api_root/statuses/update.xml") || die "Unable to exec for tweet update: $!";
                # Not reached here
        }
        my $twitter_response;
        {
                local $/ = undef;
                $twitter_response = <KID_TO_READ>;
                close KID_TO_READ;
        }
        return $twitter_response;
}

sub saved_status_changed_cb
{
        my ($new_status, $old_status) = @_;

        # For some reason, calling methods on arguments passed don't work, so fetch afresh
        $new_status = Purple::SavedStatus::get_default();
        if (! $new_status->is_transient()) {
                Purple::Debug::info($log_category, "Changed to a Saved Status, ignoring\n");
                return;
        }
        my $status_message_escaped = $new_status->get_message();
        # There should be a better way to unescape the XML encoded string
        my $status_xml = XML::XPath->new(xml=>"<status>$status_message_escaped</status>");
        my @status_nodes = $status_xml->find('/status')->get_nodelist();
        my $new_status_message = ($status_nodes[0])->string_value;

        my $twitter_response = send_tweet($new_status_message);

        if (merge_twitter_response $twitter_response, '/status') {
                # We successfully updated status, let's reset timeout
                schedule_status_update;
                my $switch_to = Purple::Prefs::get_string("$pref_root/savedstatus_to_switch_after_tweetback");
                if ($switch_to) {
                        my $saved_status = Purple::SavedStatus::find($switch_to);
                        # This would make a recursive call, but we only activate a saved status
                        $saved_status->activate() if ($saved_status && ! $saved_status->is_transient());
                }
        }
}

sub plugin_init
{
    return %PLUGIN_INFO;
}

sub plugin_load
{
    $plugin_instance = shift;
    Purple::Debug::info($log_category, "plugin_load() - Twitter Status Feed.\n");

    # Here we are adding a set of preferences
    #  The second argument is the default value for the preference.
    Purple::Prefs::add_none("$pref_root");
    Purple::Prefs::add_string("$pref_root/twitterusername", '');
    Purple::Prefs::add_string("$pref_root/twitterpassword", '');
    Purple::Prefs::add_string("$pref_root/filter_regex", '');
    Purple::Prefs::add_bool("$pref_root/sendstatus", '');
    Purple::Prefs::add_int("$pref_root/poll_interval", 120);
    Purple::Prefs::add_bool("$pref_root/ignore_replies", 1);
    Purple::Prefs::add_int("$pref_root/max_statuses_to_fetch", 0);
    Purple::Prefs::add_string("$pref_root/api_root", 'http://twitter.com');
    Purple::Prefs::add_string("$pref_root/status_template", '%s');
    Purple::Prefs::add_string("$pref_root/savedstatuses_to_update", 'Twitter');
    Purple::Prefs::add_string("$pref_root/savedstatus_to_switch_after_tweetback", '');

    Purple::Prefs::add_none("$pref_root/state");
    Purple::Prefs::add_int("$pref_root/state/last_seen_id", 0);
    Purple::Prefs::add_int("$pref_root/state/last_updated_id", 0);
    Purple::Prefs::add_string("$pref_root/state/last_updated_text", '');

    Purple::Signal::connect(Purple::SavedStatuses::get_handle(), 'savedstatus-changed', $plugin_instance, \&saved_status_changed_cb, '');

    # Discard last seen ID optimizations for the first run, in case plugin logic has changed
    Purple::Prefs::set_int("$pref_root/state/last_seen_id", 0);

    schedule_status_update 10;
}

sub plugin_unload
{
        undef $_ foreach ($active_update_timer, $plugin_instance);
        Purple::Debug::info($log_category, "plugin_unload() - Twitter Status Feed.\n");
}

sub prefs_info_cb
{
    my ($frame, $ppref);

    # The first step is to initialize the Purple::Pref::Frame that will be returned
    $frame = Purple::PluginPref::Frame->new();

    $frame->add(Purple::PluginPref->new_with_label('Twitter Account Information'));
    $frame->add(Purple::PluginPref->new_with_name_and_label("$pref_root/twitterusername", 'Twitter User Name'));

    # Let's expose this when we are more sure about tweeting back.
    # $frame->add(Purple::PluginPref->new_with_name_and_label("$pref_root/twitterpassword", 'Twitter Password (Optional)'));

    $frame->add(Purple::PluginPref->new_with_label('Options'));
    $ppref = Purple::PluginPref->new_with_name_and_label("$pref_root/poll_interval", 'Poll Interval (in seconds)');
    $ppref->set_bounds(40, 900); # Twitter has 100 per hour IP limit, which means 36 seconds between polls
    $frame->add($ppref);
    $frame->add(Purple::PluginPref->new_with_name_and_label("$pref_root/ignore_replies", 'Ignore reply tweets'));
    $frame->add(Purple::PluginPref->new_with_name_and_label("$pref_root/status_template", 'Status message template'));
    $frame->add(Purple::PluginPref->new_with_name_and_label("$pref_root/savedstatuses_to_update", 'Saved statuses to update (comma separated)'));

    # $frame->add(Purple::PluginPref->new_with_name_and_label("$pref_root/sendstatus", 'Tweet my status message when I change it in Pidgin'));
    # $frame->add(Purple::PluginPref->new_with_name_and_label("$pref_root/savedstatus_to_switch_after_tweetback", 'Switch to this saved status after tweeting back'));

    $frame->add(Purple::PluginPref->new_with_label('Advanced Options'));
    $frame->add(Purple::PluginPref->new_with_name_and_label("$pref_root/filter_regex", 'Ignore regexp for tweets'));
    $frame->add(Purple::PluginPref->new_with_name_and_label("$pref_root/api_root", 'API Root URL'));
    $ppref = Purple::PluginPref->new_with_name_and_label("$pref_root/max_statuses_to_fetch", 'Maximum statuses to request');
    $ppref->set_bounds(0, 100); # Twitter anyway doesn't return more than 20
    $frame->add($ppref);

    return $frame;
}