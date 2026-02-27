# 1. Get the list of repos
gh repo list jengo-agency --topic composer-deploy --visibility private --json name -q '.[].name' | \

# 2. Loop through each one safely
while read -r repo; do
  echo "Setting secret for $repo..."
  
  # Redirect the key file into the command
  gh secret set GLOBAL_KEY < ~/.ssh/github-compo-deploy --repo jengo-agency/"$repo"
done