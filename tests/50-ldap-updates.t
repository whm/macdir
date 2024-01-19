#!/usr/bin/perl -w
#
# tests/50-ldap-updates.t

use Cwd;
use Carp;
use DateTime;
use Getopt::Long;
use IPC::Run qw( start pump finish run timeout );
use strict;
use Test::More qw( no_plan );

my $opt_debug;
my $opt_disable;

my $DIR_ROOT    = '../tmp/test-slapd';
my $DIR_CONFIG  = "$DIR_ROOT/slapd.d";
my $DIR_DB      = "$DIR_ROOT/db";
my $FILE_ARGS   = "$DIR_ROOT/slapd.args";
my $FILE_PID    = "$DIR_ROOT/slapd.pid";
my $LDIF_CONFIG = "$DIR_ROOT/cn-config.ldif";
my $LDIF_DB     = "$DIR_ROOT/db.ldif";
my $URI         = 'ldap://127.0.0.1:9010/';
my $DIR_BASE    = 'dc=ca-zephyr,dc=org';

my $ATTR_CONF     = '../tmp/krb5PrincipalName.conf';

my $LDAP_DUMPED;

#########################################################################
# Subroutines
#########################################################################

# -----------------------------------------------------------------------
# Dump the databases

sub dump_db {
    if (!$LDAP_DUMPED) {
        print("INFO: Dumping OpenLDAP ...");
        print(search_ldap_db($URI, '', $DIR_BASE));
        print('=' x 72 . "\n");
        $LDAP_DUMPED = 1;
    }
    return;
}

# -----------------------------------------------------------------------
# print error returns with delimiters to make them more visible

sub print_err {
    my ($test, $err) = @_;

    my $txt = "Unexpected output from $test ";
    my $sz  = 72 - length($txt);
    if ($sz < 5) {
        $sz = 5;
    }
    my $hdr = $txt . '=' x $sz;

    fail($test);
    print("$hdr\n");
    print("$err\n");
    print('=' x 72 . "\n");

    dump_db();

    return;
}

# -----------------------------------------------------------------------
# read file

sub read_file {
    my ($path) = @_;
    if (!-e $path) {
        croak "ERROR: file not found $path\n";
    }
    open(my $fd, '<', $path) or croak "ERROR: openingfile $path\n";
    my $s;
    while (<$fd>) {
        $s .= $_;
    }
    close $fd or croak "ERROR: closing file $path\n";
    return $s;
}

# -----------------------------------------------------------------------
# run a shell command line

sub run_cmd {
    my @cmd = @_;

    my $return_text = '';
    my $in;
    my $out;
    my $err;
    if ($opt_debug) {
        print "\n";
        print "Executing: " . join(' ', @cmd) . "\n";
    }
    eval { run(\@cmd, \$in, \$out, \$err, timeout(600)); };
    if ($@) {
        if ($err) {
            $err .= "\n";
        }
        $err .= 'ERROR executing:' . join(q{ }, @cmd) . "\n";
        $err .= $@;
        croak "$err\n";
    }
    if ($out) {
        $return_text .= "$out\n";
    }
    if ($err) {
        $return_text .= "ERROR: $err\n";
    }
    return $return_text;
}

# ------------------------------------------------------------------------
# Create the LDAP configuration files

sub create_config_file {
    my (%args) = @_;

    my $this_file        = $args{ldif_config_file};
    my $this_header      = $args{ldif_config_header};
    my $this_footer      = $args{ldif_config_footer};
    my @this_schema_list = @{ $args{ldif_schema_files} };

    open(my $fd, '>', $this_file)
      or croak "ERROR: writing to $this_file\n";
    print $fd $this_header . "\n";
    for my $f (@this_schema_list) {
        my $f_content = read_file($f);
        print $fd $f_content . "\n";
    }
    print $fd $this_footer . "\n";
    close $fd or croak "ERROR: closing $this_file\n";

    return;
}

sub create_config {

    my $header;
    my $footer;
    my @schema_files;

    # OpenLDAP

    $header = read_file('data/config_header.ldif');
    $header =~ s/testPIDFILEtest/$FILE_PID/xms;
    $header =~ s/testARGStest/$FILE_ARGS/xms;

    $footer = read_file('data/config_footer.ldif');
    $footer =~ s/testDBDIRtest/$DIR_DB/xms;

    @schema_files = (
        'data/schema_core.ldif',
        'data/schema_cosine.ldif',
        'data/schema_nis.ldif',
        'data/schema_inetorgperson.ldif',
        'data/schema_dhcp.ldif',
        'data/schema_krb5_kdc.ldif',
        'data/schema_openssh_lpk.ldif',
        'data/schema_pdns.ldif',
        'data/schema_pdnsdomaininfo.ldif',
        'data/schema_ca_zephyr.ldif',
        );

    create_config_file(
        ldif_config_file   => $LDIF_CONFIG,
        ldif_config_header => $header,
        ldif_config_footer => $footer,
        ldif_schema_files  => \@schema_files,
        );

    pass('create_config');
    
    return;
}

# ------------------------------------------------------------------------
# Create the inital LDAP database

sub create_db_ldif {
    my (%args) = @_;

    my $this_db        = $args{db};
    my $this_ldif = $args{db_ldif};

    open(my $fd, '>', $this_ldif)
      or croak "ERROR: opening $this_ldif\n";
    print $fd $this_db;
    close $fd or croak "ERROR: closing $this_ldif\n";;
    return;
}

sub create_dbs {
    # OpenLDAP
    my $db = read_file('data/ldap_db.ldif');
    create_db_ldif(
        db      => $db,
        db_ldif => $LDIF_DB,
        );
    return;
}

# ------------------------------------------------------------------------
# Clean out old directories if they exists and then create a new
# empty directories.

sub purge_create_dirs {
    my ($dir_list_ref) = @_;
    my @dir_list = @{ $dir_list_ref };

    my @cmd;
    my $out;
    for my $d (@dir_list) {
        if (-e $d) {
            @cmd = ('rm', '-rf', $d);
            $out = run_cmd(@cmd);
            if ($opt_debug) {
                print("Output: $out\n");
            }
        }
        @cmd = ('mkdir', '-pv', $d);
        $out = run_cmd(@cmd);
        if ($opt_debug) {
            print("Output: $out\n");
        }
    }
    return;
}

# ------------------------------------------------------------------------
# Create LDAP directories

sub create_dirs {
    my @dir_list = (
        $DIR_CONFIG,
        $DIR_DB,
        );
    purge_create_dirs(\@dir_list);
    pass('create_dirs');
    return;
}

# ------------------------------------------------------------------------
# Check return from a test for forbidden strings
# Parameters:
#      parameter 1 = $t_name, the test name
#      parameter 2 = reference to command array
#      parameter 3 = $t_out, the test output
#      parameter 4 = reference to array of strings that must be missing

sub check_missing {
    my ($t_name, $t_cmd_ref, $t_out, $t_strs_ref) = @_;
    my @cmd  = @{$t_cmd_ref};
    my @strs = @{$t_strs_ref};
    if (!$t_out) {
        pass($t_name);
    } else {
        my $err;
        for my $s (@strs) {
            my $rex = $s;
            $rex =~ s{([.()*&-])}{\\$1}xmsg;
            if ($t_out =~ /$rex/ms) {
                $err .= "ERROR: found in output: $s\n";
            }
        }
        if ($err) {
            my $msg;
            my $this_cmd = join(' ', @cmd);
            $msg .= "EXECUTING: $this_cmd\n";
            $msg .= "OUTPUT:\n";
            $msg .= $t_out . "\n";
            $msg .= ('=' x 72)  . "\n";
            $msg .= "ERROR:\n";
            $msg .= $err;
            print_err($t_name, $msg);
            dump_db();
        } else {
            pass($t_name);
        }
    }
    return;
}

# ------------------------------------------------------------------------
# Check return from a test using a full regex.
# Parameters:
#      parameter 1 = $t_name, the test name
#      parameter 2 = reference to command array
#      parameter 3 = $t_out, the test output
#      parameter 4 = reference to regex's to search for in the output

sub check_output_regex {
    my ($t_name, $t_cmd_ref, $t_out, $t_strs_ref) = @_;
    my @cmd  = @{$t_cmd_ref};
    my @strs = @{$t_strs_ref};

    if (!$t_out) {
        print_err($t_name, 'No output from command');
    } else {
        my $err;
        for my $s (@strs) {
            my $rex = $s;
            $rex =~ s{([:.()&-])}{\\$1}xmsg;
            if ($t_out !~ /$rex/xms) {
                $err .= "MISSING REGEX: $rex\n";
            }
        }
        if ($err) {
            my $msg;
            my $this_cmd = join(' ', @cmd);
            $msg .= "EXECUTING: $this_cmd\n";
            $msg .= "OUTPUT:\n";
            $msg .= $t_out . "\n";
            $msg .= ('=' x 72) . "\n";
            $msg .= "ERROR:\n";
            $msg .= $err;
            print_err($t_name, $msg);
        } else {
            pass($t_name);
        }
    }
    return;
}

# ------------------------------------------------------------------------
# Search the LDAP database

sub search_ldap_db {
    my ($this_uri, $this_filter, $this_base) = @_;
    my $this_bind_dn = 'cn=manager,' . $this_base;
    my @cmd_search = ('/usr/bin/ldapsearch',
                      '-x', '-LLL',
                      '-H', $this_uri,
                      '-o', 'ldif-wrap=no',
                      '-b', $this_base,
                      '-D', $this_bind_dn,
                      '-w', 'secret');
    if ($this_filter) {
        push(@cmd_search, $this_filter);
    }
    return run_cmd(@cmd_search);
}

# ------------------------------------------------------------------------
# Update the LDAP database

sub update_ldap_db {
    my ($this_uri, $this_base, $this_ldif) = @_;
    my $tmp_file = '../tmp/ldap-update.ldif';
    open(my $fd, '>', $tmp_file) or die("ERROR: problem opening $tmp_file");
    print($fd $this_ldif)        or die("ERROR: problem writing to $tmp_file");
    close($fd)                   or die("ERROR: problem closing $tmp_file");
    my $this_bind_dn = 'cn=manager,' . $this_base;
    my @cmd_update = ('/usr/bin/ldapmodify',
                      '-H', $this_uri,
                      '-D', $this_bind_dn,
                      '-w', 'secret',
                      '-f', $tmp_file);
    my $out = run_cmd(@cmd_update);
    if (-e $tmp_file) {
        unlink($tmp_file);
    }
    return $out;
}

# ------------------------------------------------------------------------
# Check for duplicate entries in the directory for a given filter

sub check_ldap_entry {
    my ($filter) = @_;

    my $t_name = "Search for duplicates using $filter";
    my @t_cmd = ($URI, $filter, $DIR_BASE);
    my $t_out = search_ldap_db(@t_cmd);
    my $t_cnt = 0;

    my @t_strs = ();
    my $err_base = 'ERROR: LDAP Entry Error';
    my $t_tmp = $t_out;
    while ($t_tmp =~ s/dn:\s//xms) { $t_cnt++; }
    if ($t_cnt > 1) {
        my $m = "$err_base - multiple entrys found ($t_cnt)";
        $t_out .= "\n$m";
        $m =~ s/\s/\\s+/xmsg;
        push @t_strs, $m;
    }
    if ($t_cnt < 1) {
        my $m = "$err_base - entry not found";
        $t_out .= "\n$m";
        $m =~ s/\s/\\s+/xmsg;
        push @t_strs, $m;
    }
    check_missing($t_name, \@t_cmd, $t_out, \@t_strs);

    return;
}

#########################################################################
# Main Routine
#########################################################################

# Debugging option
GetOptions(
    'debug'   => \$opt_debug,
    'disable' => \$opt_disable
);

if ($opt_disable) {
    pass('All tests with an LDAP server disabled');
    exit;
}

my @t_cmd = ();
my $t_filter;
my $t_ldif;
my $t_name;
my $t_out;
my $t_site_dn;
my @t_strs = ();
my $t_tmp;
my $t_cnt;

##############################################################################
# Create test environment
##############################################################################

# Create test directory
create_dirs();

# Create the LDAP configuation and add it to the slapd instance
create_config();

$t_name = 'OpenLDAP configuration';
@t_cmd = ('/usr/sbin/slapadd',
          '-b', 'cn=config',
          '-F', $DIR_CONFIG,
          '-l', $LDIF_CONFIG);
$t_out = run_cmd(@t_cmd);
if (!$t_out) {
    $t_out = read_file($LDIF_CONFIG);
};
@t_strs = (
    'dn:\s+ cn=config',
    'olcPidFile:\s+ ../tmp/test-slapd/slapd.pid',
    'olcArgsFile:\s+ ../tmp/test-slapd/slapd.args',
    );
check_output_regex($t_name, \@t_cmd, $t_out, \@t_strs);

# Create the top of the directory tree
create_dbs();

# OpenLDAP
$t_name = "OpenLDAP data load";
@t_cmd = ('/usr/sbin/slapadd',
          '-b', $DIR_BASE,
          '-F', $DIR_CONFIG,
          '-l', $LDIF_DB);
$t_out = run_cmd(@t_cmd);
if (!$t_out) {
    $t_out = 'Load OKAY';
}

@t_strs = (
    'Load\s+ OKAY',
    );
check_output_regex($t_name, \@t_cmd, $t_out, \@t_strs);

# Start slapd server
my $slapd_cmd = "/usr/sbin/slapd -F $DIR_CONFIG -h $URI";
if ($opt_debug) {
    print "\n";
    print "Executing: $slapd_cmd\n";
}
system($slapd_cmd);
sleep 1;

##############################################################################
# Tests
##############################################################################

# Just test to make sure we can talk to the ldap server

$t_name = 'Initial OpenLDAP slapd test';
$t_filter = 'objectclass=organization';
@t_cmd = ('ldapsearch', $t_filter);
$t_out = search_ldap_db($URI, $t_filter, $DIR_BASE);
@t_strs = (
    'dn:\s+ dc=ca-zephyr,dc=org',
    'objectClass:\s+ organization',
    'objectClass:\s+ dcObject',
    'objectClass:\s+ top',
    );
check_output_regex($t_name, \@t_cmd, $t_out, \@t_strs);

##############################################################################
# ldap tests
##############################################################################

my $this_path;

$this_path = '../usr/bin:' . $ENV{'PATH'};
$ENV{'PATH'} = $this_path;
$t_name = 'Display example configuration from perl read script';
@t_cmd = ('../perl/macdir-pw-read',  '--conf=data/macdir-pw.conf',
          '--example');
$t_out = run_cmd(@t_cmd);
@t_strs = (
    'ldap_base\s+ =\s+ dc=ca-zephyr,dc=org',
    'ldap_host\s+ =\s+ localhost',
    'ldap_port\s+ =\s+ 389',
    );
check_output_regex($t_name, \@t_cmd, $t_out, \@t_strs);

$this_path = '../usr/bin:' . $ENV{'PATH'};
$ENV{'PATH'} = $this_path;
$t_name = 'Display example configuration from perl update script';
@t_cmd = ('../perl/macdir-pw-update',  '--conf=data/macdir-pw.conf',
          '--example');
$t_out = run_cmd(@t_cmd);
@t_strs = (
    'ldap_base\s+ =\s+ dc=ca-zephyr,dc=org',
    'ldap_host\s+ =\s+ localhost',
    'ldap_port\s+ =\s+ 389',
    );
check_output_regex($t_name, \@t_cmd, $t_out, \@t_strs);

$ENV{'PATH'} = $this_path;
$ENV{'REMOTE_USER'} = 'mac@CA-ZEPHYR.ORG';
$t_name = "Search the directory for mac's f5 secrets";
@t_cmd = ('../perl/macdir-pw-read',  '--conf=data/macdir-pw.conf',
          "--filter=description=*f5*", '--debug');
$t_out = run_cmd(@t_cmd);
@t_strs = (
    'cn=f5,uid=mac,ou=people,dc=ca-zephyr,dc=org',
    'F5\s+ web\s+ site\s+ access',
    'somepassword',
    );
check_output_regex($t_name, \@t_cmd, $t_out, \@t_strs);

$ENV{'REMOTE_USER'} = 'mac@CA-ZEPHYR.ORG';
$t_name = "Proposing to add a neds-test entry";
@t_cmd = ('../perl/macdir-pw-update',  '--conf=data/macdir-pw.conf',
          'add',
          'neds-test',
          "description=Ned's Router Password",
          'czCredential=somesecret',
          'uid=ned',
          '--debug',
    );
$t_out = run_cmd(@t_cmd);
@t_strs = (
    'VALID_ATTR\s+ =\s+ cn',
    'VALID_ATTR\s+ =\s+ czCommentsVisibility',
    'VALID_ATTR\s+ =\s+ czCredential',
    'VALID_ATTR\s+ =\s+ czReadUID',
    'VALID_ATTR\s+ =\s+ czWriteUID',
    'VALID_ATTR\s+ =\s+ czComments',
    'VALID_ATTR\s+ =\s+ description',
    'VALID_ATTR\s+ =\s+ descriptionVisibility',
    'VALID_ATTR\s+ =\s+ labeledUri',
    'VALID_ATTR\s+ =\s+ labeledUriVisibility',
    'VALID_ATTR\s+ =\s+ uid',
    'VALID_ATTR\s+ =\s+ uidVisibility',
    'action:\s+ add',
    'cn:\s+ neds-test',
    'Proposing\s+ to\s+ add\s+'
        . 'cn=neds-test,uid=mac,ou=people,dc=ca-zephyr,dc=org',
    );
check_output_regex($t_name, \@t_cmd, $t_out, \@t_strs);

$ENV{'REMOTE_USER'} = 'mac@CA-ZEPHYR.ORG';
$t_name = "Adding neds-test entry";
@t_cmd = ('../perl/macdir-pw-update',  '--conf=data/macdir-pw.conf',
          'add',
          'neds-test',
          "description=Ned's Router Password",
          'czcredential=somesecret',
          'uid=ned',
          '--update',
          '--debug',
    );
$t_out = run_cmd(@t_cmd);
@t_strs = (
    'Adding\s+ entry:\s+ neds-test',
    );
check_output_regex($t_name, \@t_cmd, $t_out, \@t_strs);

$ENV{'REMOTE_USER'} = 'mac@CA-ZEPHYR.ORG';
$t_name = "Update neds-test entry";
@t_cmd = ('../perl/macdir-pw-update',  '--conf=data/macdir-pw.conf',
          'update',
          'neds-test',
          'czCredential=othersecret',
          '--update',
          '--debug',
    );
$t_out = run_cmd(@t_cmd);
@t_strs = (
    'Adding\s+ czCredential\s+ =\s+ othersecret',
    );
check_output_regex($t_name, \@t_cmd, $t_out, \@t_strs);

$ENV{'REMOTE_USER'} = 'mac@CA-ZEPHYR.ORG';
$t_name = "Delete neds-test entry";
@t_cmd = ('../perl/macdir-pw-update',  '--conf=data/macdir-pw.conf',
          'delete',
          'neds-test',
          '--update',
          '--debug',
    );
$t_out = run_cmd(@t_cmd);
@t_strs = (
    'Deleting\s+ cn=neds-test,uid=mac,ou=people,dc=ca-zephyr,dc=org',
    );
check_output_regex($t_name, \@t_cmd, $t_out, \@t_strs);

##############################################################################
# End of tests
##############################################################################

# ----------------------------------------------------------------------
# Debugging display of ldap directory contents
if ($opt_debug) {
    $t_name = 'Debugging display of OpenLDAP directory entries';
    $t_out = search_ldap_db($URI, '', $DIR_BASE);
    if (!$t_out) {
        print_err($t_name, 'No output from command');
    } else {
        print($t_out);
    }
}

# ----------------------------------------------------------------------
# Kill off the background slapd process.
if (!-e $FILE_PID) {
    print("ERROR: file not found $FILE_PID\n");
} else {
    system("kill -9 `cat $FILE_PID`");
}

exit 0;
