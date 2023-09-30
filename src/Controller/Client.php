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

    public function setDi(\Pimple\Container|null $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    /**
     * Registers a new client route.
     *
     * @param \Box_App &$app The Box_App instance.
     */
    public function register(\Box_App &$app)
    {
        // register all routers to load novnc app.js & dependencies from proxmox
        $app->get('/serviceproxmox/novnc/:filename.:fileending', 'get_novnc_appjs_filename', [], static::class);
        $app->get('/serviceproxmox/novnc/:folder/:filename.:fileending', 'get_novnc_appjs_folder_filename', [], static::class);
        $app->get('/serviceproxmox/novnc/:folder/:subfolder/:filename.:fileending', 'get_novnc_appjs_folder_subfolder_filename', [], static::class);
    }


    /**
     * Returns the filename of the NoVNC app.js file.
     *
     * @param \Box_App $app The Box_App object.
     * @param string $filename The name of the file.
     * @param string $fileending The file extension.
     * @return string The filename of the NoVNC app.js file.
     */
    public function get_novnc_appjs_filename(\Box_App $app, $filename, $fileending)
    {
        $file_path = $filename . '.' . $fileending;
        return $this->get_novnc_appjs($app, $file_path);
    }

    /**
     * Returns the filename of the NoVNC app.js file in a folder.
     *
     * @param \Box_App $app The Box_App object.
     * @param string $folder The name of the folder.
     * @param string $filename The name of the file.
     * @param string $fileending The file extension.
     * @return string The filename of the NoVNC app.js file in a folder.
     */
    public function get_novnc_appjs_folder_filename(\Box_App $app, $folder, $filename, $fileending)
    {
        $file_path = $folder . '/' . $filename . '.' . $fileending;
        return $this->get_novnc_appjs($app, $file_path);
    }

    /**
     * Returns the filename of the NoVNC app.js file in a subfolder.
     *
     * @param \Box_App $app The Box_App object.
     * @param string $folder The name of the folder.
     * @param string $subfolder The name of the subfolder.
     * @param string $filename The name of the file.
     * @param string $fileending The file extension.
     * @return string The filename of the NoVNC app.js file in a subfolder.
     */
    public function get_novnc_appjs_folder_subfolder_filename(\Box_App $app, $folder, $subfolder, $filename, $fileending)
    {
        $file_path = $folder . '/' . $subfolder . '/' . $filename . '.' . $fileending;
        return $this->get_novnc_appjs($app, $file_path);
    }


    /**
     * Returns the contents of the specified JavaScript file for the noVNC app.
     *
     * @param \Box_App $app The Box application instance.
     * @param string $file_path The path to the JavaScript file.
     * @return string The contents of the JavaScript file.
     */
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
