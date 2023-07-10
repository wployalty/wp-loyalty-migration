echo "Loyalty Migartion Auto Compress Pro pack"
current_dir="$PWD/"
echo "Current Dir $current_dir"
pack_folder="wp-loyalty-migration"
plugin_pack_folder="wployalty_migration"
folder_sperate="/"
pack_compress_folder=$current_dir$pack_folder
composer_run(){
  # shellcheck disable=SC2164
  cd "$plugin_pack_folder"
  composer install
  # shellcheck disable=SC2164
  cd $current_dir
}
copy_folder(){
  echo "Compress Dir $pack_compress_folder"
  from_folder="wployalty_migration"
  from_folder_dir=$current_dir$from_folder
  move_dir=("App" "Assets" "vendor" "wp-loyalty-migration.php")
  if [ -d "$pack_compress_folder" ]
  then
      rm -r "$pack_folder"
      mkdir "$pack_folder"
      # shellcheck disable=SC2068
      for dir in ${move_dir[@]}
      do
        cp -r "$from_folder_dir/$dir" "$pack_compress_folder/$dir"
      done
  else
      mkdir "$pack_folder"
      # shellcheck disable=SC2068
      for dir in ${move_dir[@]}
      do
        cp -r "$from_folder_dir/$dir" "$pack_compress_folder/$dir"
      done
  fi
}
zip_folder(){
  rm "$pack_folder".zip
  zip -r "$pack_folder".zip $pack_folder
  zip -d "$pack_folder".zip __MACOSX/\*
  zip -d "$pack_folder".zip \*/.DS_Store
}
composer_run
copy_folder
zip_folder

echo "End"