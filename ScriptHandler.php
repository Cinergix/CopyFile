<?php

namespace Cinergix\CopyFile;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;
use Composer\Script\Event;

class ScriptHandler
{
    /**
     * @param Event $event
     */
    public static function copy(Event $event)
    {

        $extras = $event->getComposer()->getPackage()->getExtra();

        $eventName = $event->getName();

        $copyConfig = $extras['copy-config'];

        if(isSet($copyConfig)){
            $files = self::getFiles($copyConfig, $eventName);
            if ( !isSet($files) ){
              $files = $extras['copy-file'];
            }
        }else{
          throw new \InvalidArgumentException('The dirs or files needs to be configured through the extra.copy-file setting or via a copy config file for the target event');
        }

        if ($files === array_values($files)) {
            throw new \InvalidArgumentException('The extra.copy-file must be hash like "{<dir_or_file_from>: <dir_to>}".');
        }

        $fs = new Filesystem;
        $io = $event->getIO();

        foreach ($files as $from => $to) {

            // Check the renaming of file for direct moving (file-to-file)
            $isRenameFile = substr($to, -1) != '/' && !is_dir($from);

            if (file_exists($to) && !is_dir($to) && !$isRenameFile) {
                throw new \InvalidArgumentException('Destination directory is not a directory.');
            }

            try {
                if ($isRenameFile) {
                    $fs->mkdir(dirname($to));
                } else {
                    $fs->mkdir($to);
                }
            } catch (IOException $e) {
                throw new \InvalidArgumentException(sprintf('<error>Could not create directory %s.</error>', $to));
            }

            if (false === file_exists($from)) {
                throw new \InvalidArgumentException(sprintf('<error>Source directory or file "%s" does not exist.</error>', $from));
            }

            if (is_dir($from)) {
                $finder = new Finder;
                $finder->files()->in($from);

                foreach ($finder as $file) {
                    $dest = sprintf('%s/%s', $to, $file->getRelativePathname());

                    try {
                        $fs->copy($file, $dest);
                    } catch (IOException $e) {
                        throw new \InvalidArgumentException(sprintf('<error>Could not copy %s</error>', $file->getBaseName()));
                    }
                }
            } else {
                try {
                    if ($isRenameFile) {
                        $fs->copy($from, $to);
                    } else {
                        $fs->copy($from, $to.'/'.basename($from));
                    }
                } catch (IOException $e) {
                    throw new \InvalidArgumentException(sprintf('<error>Could not copy %s</error>', $from));
                }
            }

            $io->write(sprintf('Copied file(s) from <comment>%s</comment> to <comment>%s</comment>.', $from, $to));
        }
    }

    private static function getFiles($configFile, $eventName){

      if( isSet( $configFile ) ){
        include $configFile;
        if ( isSet($copyFiles[$eventName]) ){
          return $copyFiles[$eventName];
        }else{
          return;
        }
      }else {
        throw new \InvalidArgumentException('The copy/deployment instruction file location needs to be configured through the extra.copy-config setting.');
      }

    }
}
