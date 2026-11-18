#!/usr/bin/env bash
#
# Integration test of file uploads, over real HTTP requests (is_uploaded_file()
# is false in `wp eval-file`, so run.php cannot cover them).
#
# Usage, from anywhere, against a LOCAL or staging site where the plugin is
# active (never production):
#
#   tests/integration/file-upload-test.sh <site-dir> <site-url> [wp command]
#   tests/integration/file-upload-test.sh ~/Sites/demo https://demo.test "/opt/homebrew/opt/php@8.3/bin/php /opt/homebrew/bin/wp"
#
# It creates a temporary template (spam protection None, messages not saved,
# JPG/PNG/PDF up to 1 MB, a required file field), a second one with fields
# taking several files ([pieces* files] [extras files], 2 files each, messages
# saved) and a temporary mu-plugin whose pre_wp_mail filter records the
# attachments and stops every email; all of them, the messages of the second
# template and the mail-log rows of the run are removed on exit, even on
# failure.

set -u

SITE=${1:?site directory}
URL=${2:?site URL}
WP=${3:-wp}

cd "$SITE" || exit 2

WORK=$(mktemp -d)
MU="$SITE/wp-content/mu-plugins/lcmt-it-upload-capture.php"
KEY="it-upload-$(date +%s)$RANDOM"
KEY2="${KEY}-multi"
TO="it-upload@example.invalid"
POST_ID=""
POST_ID2=""
FAILED=0

wp_eval() { $WP eval "$1" 2>>"$WORK/wp-errors.log"; }

cleanup() {
    [ -n "$POST_ID" ] && wp_eval "wp_delete_post($POST_ID, true);" >/dev/null
    [ -n "$POST_ID2" ] && wp_eval "wp_delete_post($POST_ID2, true);" >/dev/null
    wp_eval "global \$wpdb; \$wpdb->delete(\$wpdb->prefix . 'lcmt_mailer_submissions', ['form_key' => '$KEY2']);" >/dev/null
    # Rows a mail-logging plugin may have recorded for the test address.
    wp_eval "global \$wpdb; \$t = \$wpdb->prefix . 'wpml_mails'; if (\$wpdb->get_var(\"SHOW TABLES LIKE '\$t'\") === \$t) { \$wpdb->query(\$wpdb->prepare(\"DELETE FROM \$t WHERE receiver LIKE %s\", '%$TO%')); }" >/dev/null
    rm -f "$MU"
    rm -rf "$WORK"
}
trap cleanup EXIT

ok()   { echo "  ok    $1"; }
fail() { echo "  FAIL  $1"; FAILED=1; }

# ---- Temporary template and capture ----------------------------------------

POST_ID=$(wp_eval "
\$id = wp_insert_post(['post_type' => 'mail', 'post_status' => 'publish', 'post_title' => 'IT upload']);
foreach ([
    '_lcmt_mail_key' => '$KEY', '_lcmt_mail_to' => '$TO', '_lcmt_mail_subject' => 'Upload [nom*]',
    '_lcmt_mail_content' => '[nom*] [fichier* file]', '_lcmt_mail_captcha' => 'none',
    '_lcmt_mail_store_submissions' => '0', '_lcmt_mail_file_formats' => 'jpg,png,pdf', '_lcmt_mail_file_max_size' => '1',
] as \$k => \$v) { update_post_meta(\$id, \$k, \$v); }
echo \$id;")

[ -n "$POST_ID" ] || { echo "Could not create the template:"; cat "$WORK/wp-errors.log"; exit 2; }

POST_ID2=$(wp_eval "
\$id = wp_insert_post(['post_type' => 'mail', 'post_status' => 'publish', 'post_title' => 'IT upload multi']);
foreach ([
    '_lcmt_mail_key' => '$KEY2', '_lcmt_mail_to' => '$TO', '_lcmt_mail_subject' => 'Upload [nom*]',
    '_lcmt_mail_content' => 'Pieces: [pieces* files] Extras: [extras files]', '_lcmt_mail_captcha' => 'none',
    '_lcmt_mail_store_submissions' => '1', '_lcmt_mail_file_formats' => 'jpg,png,pdf', '_lcmt_mail_file_max_size' => '1',
    '_lcmt_mail_file_max_files' => '2',
] as \$k => \$v) { update_post_meta(\$id, \$k, \$v); }
echo \$id;")

[ -n "$POST_ID2" ] || { echo "Could not create the second template:"; cat "$WORK/wp-errors.log"; exit 2; }

cat > "$MU" <<PHP
<?php
// TEMPORARY (tests/integration/file-upload-test.sh): records the test mails' attachments, sends nothing.
add_filter('pre_wp_mail', static function (\$return, \$atts) {
    if (strpos((string) (is_array(\$atts['to']) ? implode(',', \$atts['to']) : \$atts['to']), '$TO') === false) {
        return \$return;
    }
    \$files = [];
    foreach ((array) (\$atts['attachments'] ?? []) as \$path) {
        \$files[] = ['path' => \$path, 'name' => basename(\$path), 'exists' => is_file(\$path), 'size' => @filesize(\$path),
            'perms' => substr(sprintf('%o', @fileperms(\$path)), -3), 'dir' => substr(sprintf('%o', @fileperms(dirname(\$path))), -3)];
    }
    file_put_contents('$WORK/mails.jsonl', wp_json_encode(\$files) . "\n", FILE_APPEND);
    file_put_contents('$WORK/messages.jsonl', wp_json_encode((string) \$atts['message']) . "\n", FILE_APPEND);

    return !file_exists('$WORK/fail');
}, 10, 2);
PHP

# The web server's temp dir can differ from WP-CLI's: the copies are looked
# for where the server made them (the folders recorded by the capture).
copy_dirs() { grep -o '"path":"[^"]*"' "$WORK/mails.jsonl" 2>/dev/null | sed 's/"path":"//; s/"$//; s#\\/#/#g' | xargs -I{} dirname {}; }
web_temp() { copy_dirs | awk 'NR == 1' | xargs -I{} dirname {}; }
leftovers() { # copies still on disk, plus any lcmt-mailer-* folder in the server's temp dir
    local n=0 d
    for d in $(copy_dirs); do [ -e "$d" ] && n=$((n + 1)); done
    local t; t=$(web_temp)
    [ -n "$t" ] && n=$((n + $(find "$t" -maxdepth 1 -name 'lcmt-mailer-*' 2>/dev/null | wc -l | tr -d ' ')))
    echo "$n"
}
BEFORE=0

# ---- Fixtures --------------------------------------------------------------

printf '%%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%%%EOF\n' > "$WORK/devis.pdf"
printf '\x89PNG\r\n\x1a\n\0\0\0\rIHDR\0\0\0\1\0\0\0\1\x08\x06\0\0\0\x1f\x15\xc4\x89\0\0\0\rIDATx\x9cc\xf8\x0f\0\0\x01\x01\0\x05\x18\xd8N\0\0\0\0IEND\xaeB`\x82' > "$WORK/image.png"
cp "$WORK/image.png" "$WORK/photo.jpg"                       # PNG content, .jpg name
printf 'just text\n' > "$WORK/notes.txt"
cp "$WORK/notes.txt" "$WORK/faux.pdf"                         # text content, .pdf name
printf '\xff\xd8\xff\xe0\0\x10JFIF\0' > "$WORK/shell.php"     # JPEG header, .php name
{ cat "$WORK/devis.pdf"; head -c 1200000 /dev/zero; } > "$WORK/gros.pdf"   # over 1 MB

ENDPOINT="$URL/wp-json/lcmt-mailer/v1/forms/$KEY"
post() { curl -sk -o "$WORK/answer.json" -w '%{http_code}' "$ENDPOINT" "$@"; }
mails() { [ -f "$WORK/mails.jsonl" ] && wc -l < "$WORK/mails.jsonl" | tr -d ' ' || echo 0; }

expect() { # expect <label> <code> <curl args...>
    local label=$1 code=$2; shift 2
    local sent; sent=$(mails)
    local got; got=$(post "$@")
    if [ "$got" = "$code" ]; then ok "$label ($got)"; else fail "$label: HTTP $got, expected $code — $(head -c 300 "$WORK/answer.json")"; fi
    if [ "$code" != "200" ] && [ "$code" != "500" ] && [ "$(mails)" != "$sent" ]; then fail "$label: an email was sent"; fi
}

echo "File uploads ($ENDPOINT)"

# ---- Accepted ---------------------------------------------------------------

expect "an allowed PDF is accepted" 200 -F nom=Test -F "fichier=@$WORK/devis.pdf"
LAST=$(tail -n 1 "$WORK/mails.jsonl" 2>/dev/null)
case "$LAST" in
    *'"name":"devis.pdf"'*'"exists":true'*) ok "the mail carries the file under its name" ;;
    *) fail "attachment not found in the mail: $LAST" ;;
esac
case "$LAST" in *'"perms":"600"'*'"dir":"700"'*) ok "the copy is private (0600 in a 0700 folder)" ;; *) fail "copy permissions: $LAST" ;; esac
[ "$(leftovers)" = "$BEFORE" ] && ok "the copy is deleted after sending" || fail "a temp folder is left after sending"

expect "an allowed PNG is accepted" 200 -F nom=Test -F "fichier=@$WORK/image.png"

# ---- Refused ----------------------------------------------------------------

expect "a format that is not allowed is refused" 422 -F nom=Test -F "fichier=@$WORK/notes.txt"
expect "text renamed .pdf is refused" 422 -F nom=Test -F "fichier=@$WORK/faux.pdf"
expect "a PNG renamed .jpg is refused" 422 -F nom=Test -F "fichier=@$WORK/photo.jpg"
expect "a JPEG named .php is refused" 422 -F nom=Test -F "fichier=@$WORK/shell.php;type=image/jpeg"
expect "a file over the size limit is refused" 422 -F nom=Test -F "fichier=@$WORK/gros.pdf"
expect "a missing required file is refused" 422 -F nom=Test
expect "two files under one name are refused" 422 -F nom=Test -F "fichier[]=@$WORK/devis.pdf" -F "fichier[]=@$WORK/image.png"
expect "a file name sent as text is refused" 422 -F nom=Test -F fichier=devis.pdf
expect "a JSON body cannot fill a file field" 422 -H 'Content-Type: application/json' --data '{"nom":"Test","fichier":"devis.pdf"}'
[ "$(leftovers)" = "$BEFORE" ] && ok "refused files leave no copy" || fail "a temp folder is left after a refusal"

# ---- Failed send ------------------------------------------------------------

touch "$WORK/fail"
expect "a failed send answers 500" 500 -F nom=Test -F "fichier=@$WORK/devis.pdf"
[ "$(leftovers)" = "$BEFORE" ] && ok "the copy is deleted after a failed send" || fail "a temp folder is left after a failed send"

rm -f "$WORK/fail"

# ---- Several files ([pieces* files] [extras files], 2 files each) ----------

ENDPOINT="$URL/wp-json/lcmt-mailer/v1/forms/$KEY2"
echo "Several files ($ENDPOINT)"

expect "two allowed files are accepted" 200 -F nom=Test -F "pieces[]=@$WORK/devis.pdf" -F "pieces[]=@$WORK/image.png"
LAST=$(tail -n 1 "$WORK/mails.jsonl" 2>/dev/null)
case "$LAST" in
    *'"name":"devis.pdf"'*'"exists":true'*'"name":"image.png"'*'"exists":true'*) ok "the mail carries both files under their names" ;;
    *) fail "attachments not found in the mail: $LAST" ;;
esac
[ "$(grep -o '"perms":"600"' <<<"$LAST" | wc -l | tr -d ' ')" = 2 ] && [ "$(grep -o '"dir":"700"' <<<"$LAST" | wc -l | tr -d ' ')" = 2 ] \
    && ok "each copy is private (0600 in its own 0700 folder)" || fail "copy permissions: $LAST"
[ "$(copy_dirs | tail -n 2 | sort -u | wc -l | tr -d ' ')" = 2 ] && ok "each file has a folder of its own" || fail "the copies share a folder"
tail -n 1 "$WORK/messages.jsonl" | grep -q 'Pieces: devis.pdf, image.png' && ok "the mail lists the file names" || fail "file names not in the mail: $(tail -n 1 "$WORK/messages.jsonl" | head -c 300)"
[ "$(leftovers)" = "$BEFORE" ] && ok "the copies are deleted after sending" || fail "a temp folder is left after sending"
STORED=$(wp_eval "global \$wpdb; echo \$wpdb->get_var(\$wpdb->prepare('SELECT fields FROM ' . \$wpdb->prefix . 'lcmt_mailer_submissions WHERE form_key = %s ORDER BY id DESC LIMIT 1', '$KEY2'));")
case "$STORED" in
    *'"name":"pieces","type":"files","value":"devis.pdf, image.png"'*) ok "the received message keeps the file names only" ;;
    *) fail "received message: $STORED" ;;
esac

expect "a single file sent as pieces[] is accepted" 200 -F nom=Test -F "pieces[]=@$WORK/image.png"
expect "each field takes its own number of files" 200 -F nom=Test -F "pieces[]=@$WORK/devis.pdf" -F "pieces[]=@$WORK/image.png" \
    -F "extras[]=@$WORK/devis.pdf" -F "extras[]=@$WORK/image.png"
[ "$(tail -n 1 "$WORK/mails.jsonl" | grep -o '"exists":true' | wc -l | tr -d ' ')" = 4 ] && ok "the four files are attached" || fail "attachments: $(tail -n 1 "$WORK/mails.jsonl")"
[ "$(leftovers)" = "$BEFORE" ] && ok "the four copies are deleted after sending" || fail "a temp folder is left after sending"

expect "more files than allowed are refused" 422 -F nom=Test -F "pieces[]=@$WORK/devis.pdf" -F "pieces[]=@$WORK/image.png" -F "pieces[]=@$WORK/devis.pdf"
grep -q 'accepts 2 files at most\|accepte 2 fichiers au maximum' "$WORK/answer.json" && ok "the error says how many files the field takes" || fail "answer: $(head -c 300 "$WORK/answer.json")"
expect "an allowed file next to a refused one is refused" 422 -F nom=Test -F "pieces[]=@$WORK/devis.pdf" -F "pieces[]=@$WORK/notes.txt"
grep -q 'notes.txt' "$WORK/answer.json" && ok "the error names the refused file" || fail "answer: $(head -c 300 "$WORK/answer.json")"
expect "a PNG renamed .jpg among the files is refused" 422 -F nom=Test -F "pieces[]=@$WORK/image.png" -F "pieces[]=@$WORK/photo.jpg"
expect "text renamed .pdf among the files is refused" 422 -F nom=Test -F "pieces[]=@$WORK/faux.pdf"
expect "a JPEG named .php among the files is refused" 422 -F nom=Test -F "pieces[]=@$WORK/shell.php;type=image/jpeg"
expect "a file over the size limit among the files is refused" 422 -F nom=Test -F "pieces[]=@$WORK/devis.pdf" -F "pieces[]=@$WORK/gros.pdf"
expect "a missing required field of files is refused" 422 -F nom=Test -F "extras[]=@$WORK/devis.pdf"
expect "file names sent as text are refused" 422 -F nom=Test -F "pieces[]=devis.pdf"
expect "a JSON body cannot fill a field of files" 422 -H 'Content-Type: application/json' --data '{"nom":"Test","pieces":["devis.pdf"]}'
expect "a nested name is refused" 422 -F nom=Test -F "pieces[a][b]=@$WORK/devis.pdf"
[ "$(leftovers)" = "$BEFORE" ] && ok "refused files leave no copy" || fail "a temp folder is left after a refusal"

touch "$WORK/fail"
expect "a failed send with several files answers 500" 500 -F nom=Test -F "pieces[]=@$WORK/devis.pdf" -F "pieces[]=@$WORK/image.png"
[ "$(leftovers)" = "$BEFORE" ] && ok "the copies are deleted after a failed send" || fail "a temp folder is left after a failed send"
rm -f "$WORK/fail"

if [ "$FAILED" = 0 ]; then echo "Success: file uploads"; else echo "Error: file uploads"; fi
exit "$FAILED"
