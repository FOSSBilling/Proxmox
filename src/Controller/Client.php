<?php

/**
 * Proxmox module for FOSSBilling
 *
 * @author   FOSSBilling (https://www.fossbilling.org) & Anuril (https://github.com/anuril) 
 * @license  GNU General Public License version 3 (GPLv3)
 *
 * This software may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 * Original Author: Scitch (https://github.com/scitch)
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3) that is bundled
 * with this source code in the file LICENSE. 
 * This Module has been written originally by Scitch (https://github.com/scitch) and has been forked from the original BoxBilling Module.
 * It has been rewritten extensively.
 */

namespace Box\Mod\Serviceproxmox\Controller;

class Client implements \FOSSBilling\InjectionAwareInterface
{
    protected ?\Pimple\Container $di;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function register(\Box_App &$app)
    {
        // register all routers to load novnc app.js & dependencies from proxmox
        $app->get('/serviceproxmox/novnc/:filename.:fileending', 'get_novnc_appjs_filename', [], static::class);
        $app->get('/serviceproxmox/novnc/:folder/:filename.:fileending', 'get_novnc_appjs_folder_filename', [], static::class);
        $app->get('/serviceproxmox/novnc/:folder/:subfolder/:filename.:fileending', 'get_novnc_appjs_folder_subfolder_filename', [], static::class);
    }

    // create functions to call get_novnc_appjs with paths
    // create function for only filename
    public function get_novnc_appjs_filename(\Box_App $app, $filename, $fileending)
    {
        $file_path = $filename . '.' . $fileending;
        return $this->get_novnc_appjs($app, $file_path);
    }
    // create function for filename and folder
    public function get_novnc_appjs_folder_filename(\Box_App $app, $folder, $filename, $fileending)
    {
        $file_path = $folder . '/' . $filename . '.' . $fileending;
        return $this->get_novnc_appjs($app, $file_path);
    }
    // create function for filename, folder and subfolder
    public function get_novnc_appjs_folder_subfolder_filename(\Box_App $app, $folder, $subfolder, $filename, $fileending)
    {
        $file_path = $folder . '/' . $subfolder . '/' . $filename . '.' . $fileending;
        return $this->get_novnc_appjs($app, $file_path);
    }


    // create get_novnc_appjs function
    public function get_novnc_appjs(\Box_App $app, $file_path)
    {
        $api = $this->di['api_client'];
        // print out $file;
        // build path 
        $request_response = $api->Serviceproxmox_novnc_appjs_get($file_path);
        // get content and content type from response
        $content = $request_response->getContent();
        $content_headers = $request_response->getHeaders();

        header("Content-type: " . $content_headers['Content-Type']);
        // replace every occurence of /novnc/ with /serviceproxmox/novnc/
        $content = str_replace('/novnc/', '/serviceproxmox/novnc/', $content);
        return $content;
    }
}
