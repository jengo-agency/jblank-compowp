# 1. Create the necessary directories
mkdir -p ~/tmp/5d
mkdir -p ~/tmp/30d
mkdir -p ~/.config/user-tmpfiles.d/

# 2. Create the permanent warning files inside the folders
echo "⚠️ WARNING: This folder is automatically purged. Files older than 1 DAY are deleted." > ~/tmp/AUTOPURGE_WARNING.txt
echo "⚠️ WARNING: This folder is automatically purged. Files older than 5 DAYS are deleted." > ~/tmp/5d/AUTOPURGE_WARNING.txt
echo "⚠️ WARNING: This folder is automatically purged. Files older than 30 DAYS are deleted." > ~/tmp/30d/AUTOPURGE_WARNING.txt

# 3. Create the configuration file with the 'd' (delete) and 'x' (exclude) rules
cat << 'EOF' > ~/.config/user-tmpfiles.d/mycache.conf
# The /tmp rule (only affects your user's files)
d /tmp - - - 1d

# The ~/tmp 1-day rules
d %h/tmp - - - 1d
x %h/tmp/AUTOPURGE_WARNING.txt

# The ~/tmp/5d rules
d %h/tmp/5d - - - 5d
x %h/tmp/5d/AUTOPURGE_WARNING.txt

# The ~/tmp/30d rules
d %h/tmp/30d - - - 30d
x %h/tmp/30d/AUTOPURGE_WARNING.txt

# The WordPress themes rule
# Updated to the accurate path for demo themes
d %h/wp-content/themes - - - 1d
EOF

# 4. Restart the timer to immediately load the new config file
systemctl --user restart systemd-tmpfiles-clean.timer
systemctl --user enable --now systemd-tmpfiles-clean.timer
loginctl enable-linger $USER

# 5. Create the documentation file in your home directory
cat << 'EOF' > ~/CLEAR_TMP.md
# Auto clear temp files is activated

## Dirs
* `/tmp` : 1d (Note: Only affects files owned by this user)
* `~/tmp/` : 1d
* `~/tmp/5d` : 5d
* `~/tmp/30d` : 30d
* `~/wp-content/themes` : 1d

## Exclusions
* `AUTOPURGE_WARNING.txt` files inside the tmp folders will never be deleted.

## Control
* `cat ~/.config/user-tmpfiles.d/mycache.conf` to see the schedule rules
* `systemctl --user status systemd-tmpfiles-clean.timer` to check the daemon is active and see the next scheduled run
* `systemd-tmpfiles --user --clean` to manually force a cleanup right now
EOF

echo "✅ Setup complete! Warning files created and protected."
