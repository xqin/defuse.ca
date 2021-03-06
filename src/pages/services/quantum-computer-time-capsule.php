<?php

require_once('/storage/creds.php');
require_once('libs/HtmlEscape.php');
require_once('libs/TimeCapsule.php');

Upvote::render_arrows(
    "quantumcomputertimecapsule",
    "defuse_pages",
    "Send a Message to the Future",
    "Save a message that will become readable after quantum computers are built.",
    "https://defuse.ca/quantum-computer-time-capsule.htm"
);

function time_for_human($seconds)
{
    if ($seconds > 24*3600) {
        return round($seconds/(24*3600)) . " days";
    } else if ($seconds > 3600) {
        return round($seconds/3600) . " hours";
    } else if ($seconds > 60) {
        return round($seconds/60) . " minutes";
    } else {
        return $seconds . " seconds";
    }
}

// Copied from: https://stackoverflow.com/a/30749288
function checkReCAPTCHA() 
{
    try {
        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $creds = Creds::getCredentials("timecapsule_recaptcha");
        $data = ['secret'   => $creds[C_PASS],
                 'response' => $_POST['g-recaptcha-response'],
                 'remoteip' => $_SERVER['REMOTE_ADDR']];

        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data) 
            ]
        ];

        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        return json_decode($result)->success;
    }
    catch (Exception $e) {
        return null;
    }
}

?>

<script type="text/javascript" src="/timecapsule/tweetnacl-time-capsule.js"></script>
<script type="text/javascript" src="/timecapsule/tweetnacl-util-time-capsule.js"></script>
<script type="text/javascript" src="/timecapsule/timecapsule-save.js"></script>

<h1>Time Capsule: Send a Message to the Future</h1>

<?php
    $textarea_contents = '';
    if (isset($_POST['ciphertext'])) {
        // The client sends us the plaintext message in case there's an error
        // and we need to echo it back to them. Make sure not to save it
        // anywhere!
        $textarea_contents = $_POST['message'];

        // Record the date and time.
        $date_utc = new \DateTime(null, new \DateTimeZone("UTC"));
        $formatted_date = $date_utc->format(\DateTime::ATOM);

        // Put all of the information necessary for a quantum computer to
        // decrypt the message on a single line.
        $encrypted_message = 'time:' . $formatted_date .
            ' algorithm:' . $_POST['algorithm'] .
            ' presentpublickey:' . $_POST['present_public_key'] .
            ' futurepublickey:' . $_POST['future_public_key'] .
            ' ciphertext:' . $_POST['ciphertext'];

        // Make sure it's not too big and actually is a single line.
        if (strlen($encrypted_message) >= 200000 || strpos($encrypted_message, "\n") !== false || strpos($encrypted_message, "\r") !== false) {
            // This should never happen unless the user is intentionally
            // bypassing the soft-limit in the HTML form or modifying their
            // local JavaScript code.
            ?>
            <p style="font-weight: bold; color: red;">Something went wrong, your message was too big or the encrypted version contains newlines.</p>
            <?
        // Check the CAPTCHA.
        } else if (checkReCAPTCHA() !== true) {
            ?>
            <p style="font-weight: bold; color: red;">Please solve the CAPTCHA.</p>
            <?
        // Save it to the database.
        } else if (TimeCapsule::add_entry($encrypted_message) !== true) {
            ?>
            <p style="font-weight: bold; color: red;">Sorry, there was an error adding the message to the database.</p>
            <?
        } else {
            // The message has been added, so it's safe to delete the plaintext.
            $textarea_contents = '';
            ?>
            <p>
                <span style="font-weight: bold; color: green;">Your message has been sent to
                the future!</span> The text below is your message, encrypted so that it can't be
                read until someone builds a large-scale quantum computer. You don't need to save
                it yourself; it has already been added to the archive along with all of the
                other messages to the future. To help the archive survive into the future, you
                can <a href="/timecapsule/quantum-computer-time-capsule-download.php"
                target="_blank">download a copy of the archive</a> and save it somewhere
                a future historian might find it.
            </p>

            <div
                style="font: monospace; width: 80%; margin: 0 auto; word-break: break-all; word-wrap: break-word; color: grey; background-color: white; border: none;"
            ><?php echo HtmlEscape::escapeText($encrypted_message, false, 4); ?></div>

            <h2>Send Another Message</h2>
            <?
        }
    }
?>

<script>
    function onRecaptchaChecked() {
        document.getElementById("submitbutton").disabled = false;
    }
    function onRecaptchaExpired() {
        document.getElementById("submitbutton").disabled = true;
    }
</script>

<form id="messageform" action="/quantum-computer-time-capsule.htm" method="post">
    <center>
        <textarea
            id="message"
            name="message"
            rows="15"
            cols="80"
            style="color: black; background-color: white; border: dashed 1px black;"
            maxlength="100000"
        ><?php echo htmlentities($textarea_contents, ENT_QUOTES); ?></textarea>
        <br /> <br />
        <div class="g-recaptcha" data-sitekey="6LcnNi8UAAAAALJikXrc6jwNWUm00Yjx_rHCJW7u" data-callback="onRecaptchaChecked" data-expired-callback="onRecaptchaExpired"></div>
        <br />
        <input type="button" name="send" id="submitbutton" value="Send Message to the Future" onclick="sendMessage();" disabled>
        <p style="color: grey;">(100,000 characters maximum.)</p>
        <input type="hidden" id="algorithm" name="algorithm" value="" />
        <input type="hidden" id="present_public_key" name="present_public_key" value="" />
        <input type="hidden" id="future_public_key" name="future_public_key" value="" />
        <input type="hidden" id="ciphertext" name="ciphertext" value="" />
    </center>
</form>

<h2>How it Works</h2>

<p>
Using cryptography, we can encrypt a message and throw away the key so that it
would take a normal (classical) computer millions of years to recover the
original message. If we do the encryption using elliptic-curve cryptography,
then a classical computer will still need millions of years to recover the
message, but a large-scale <i>quantum computer</i>, if we had one, would be able
to recover the message in a matter of weeks. This gives us a unique opportunity
to save messages in a format that won't be possible to read until somebody
manages to build a quantum computer.
</p>

<p>
So, use the form above to encrypt and upload a message that will remain
unreadable until either someone builds a large-scale quantum computer or, in an
unexpected breakthrough, someone discovers a way to recover the messages using
a classical computer. It's not known how long it will be before we can build
large-scale quantum computers. Some scientists guess that we'll see one within
the next 10 years, others guess that it'll take 50 years or more, and some don't
think we'll ever be able to build one. So, it's impossible to say when your
message will be read, if at all. But it's worth a try!
</p>


<h2>Message Archive</h2>

<p>
It might take a long time for humanity to invent the technology we need to read
these messages, and this website will probably go down before then. So, after
you've added your message, please consider downloading the entire archive so
that a future historian might one day find your copy of it.
</p>

<form action="/timecapsule/quantum-computer-time-capsule-download.php" method="get" target="_blank">
    <center>
        <input type="submit" value="Download Archive To-Date" />
    </center>
</form>

<p>
The archive currently contains <?php
    $count = TimeCapsule::get_message_count();
    if ($count) {
        echo (int)$count;
    } else {
        echo "ERROR";
    }
?> messages.
The last message was added <?php
    $last = TimeCapsule::get_last_timestamp();
    if ($last) {
        echo htmlentities(time_for_human(time() - $last), ENT_QUOTES);
    } else {
        echo "ERROR";
    }
?> ago.
</p>

<p>
Future historians would like to know that their version of the archive is
authentic. To help prevent fakes, once a year I will embed a cryptographic hash
of the archive into a cryptocurrency's blockchain. Historians will be able to
check the blockchain's "proof of work" so that anyone trying to rewrite
years-past history will need to have enormous computational resources at their
disposal.
</p>

<p>Checkpoints:</p>

<ol>
    <li>
        The SHA256 hash
        f4233140fde1f1efe75d867eeb17cc34ae91a0165f767d6908ed7e3e3bb33b09 of the first
        1338 lines of the archive can be found in transaction
        ff8221a3ee3322b36ef5817a0a591d7001c0fca2f549b5aa0c2a2b712fe6315a in
        block 178,991 of the Zcash blockchain.
    </li>
    <li>The SHA256 hash
        4c72f9ca9a5ea691ed694f6091263d229752f6424cd6048dfbfda3f2498ffa3a of the first
        1825 lines of the archive can be found in transaction
        30ae065eb7d6860776abaa0b1fbb02144d8295a4c803ec44118204b61f8cfa29 in block 476,431 of
        the Zcash blockchain.
    </li>
</ol>
