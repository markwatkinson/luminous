#!/usr/bin/perl



some_function(<<EOF, 2);
argument 1
EOF


my $sender = "Buffy the Vampire Slayer";
my $recipient = "Spike";

print <<"END";

Dear $recipient,

I wish you to leave Sunnydale and never return.

Not Quite Love,
$sender

END


1234

print <<'END';
Dear $recipient,

I wish you to leave Sunnydale and never return.

Not Quite Love,
$sender
END

123
my $shell_script_stdout = <<`END`;
echo foo
echo bar
END