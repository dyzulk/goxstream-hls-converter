$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
node "$ScriptDir/node_modules/tsx/dist/cli.mjs" "$ScriptDir/scripts/ghc/src/index.ts" $args
