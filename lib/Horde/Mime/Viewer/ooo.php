<?php
/**
 * The Horde_Mime_Viewer_ooo class renders out OpenOffice.org documents in
 * HTML format.
 *
 * Copyright 2003-2008 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Marko Djukic <marko@oblo.com>
 * @author  Jan Schneider <jan@horde.org>
 * @package Horde_Mime_Viewer
 */
class Horde_Mime_Viewer_ooo extends Horde_Mime_Viewer_Driver
{
    /**
     * Can this driver render various views?
     *
     * @var boolean
     */
    protected $_capability = array(
        'embedded' => false,
        'full' => true,
        'info' => false,
        'inline' => false
    );

    /**
     * Return the full rendered version of the Horde_Mime_Part object.
     *
     * @return array  See Horde_Mime_Viewer_Driver::render().
     */
    protected function _render()
    {
        $has_xslt = Util::extensionExists('xslt');
        $has_ssfile = function_exists('domxml_xslt_stylesheet_file');
        if (($use_xslt = $has_xslt || $has_ssfile)) {
            $tmpdir = Util::createTempDir(true);
        }

        $fnames = array('content.xml', 'styles.xml', 'meta.xml');
        $tags = array(
            'text:p' => 'p',
            'table:table' => 'table border="0" cellspacing="1" cellpadding="0" ',
            'table:table-row' => 'tr bgcolor="#cccccc"',
            'table:table-cell' => 'td',
            'table:number-columns-spanned=' => 'colspan='
        );

        require_once 'Horde/Compress.php';
        $zip = &Horde_Compress::singleton('zip');
        $list = $zip->decompress($this->_mimepart->getContents(), array('action' => HORDE_COMPRESS_ZIP_LIST));

        foreach ($list as $key => $file) {
            if (in_array($file['name'], $fnames)) {
                $content = $zip->decompress($this->_mimepart->getContents(), array(
                    'action' => HORDE_COMPRESS_ZIP_DATA,
                    'info' => $list,
                    'key' => $key
                ));

                if ($use_xslt) {
                    file_put_contents($tmpdir . $file['name'], $content);
                } elseif ($file['name'] == 'content.xml') {
                    return str_replace(array_keys($tags), array_values($tags), $content);
                }
            }
        }

        if (!Util::extensionExists('xslt')) {
            return;
        }

        $xsl_file = dirname(__FILE__) . '/ooo/main_html.xsl';

        if ($has_ssfile) {
            /* Use DOMXML */
            $xslt = domxml_xslt_stylesheet_file($xsl_file);
            $dom  = domxml_open_file($tmpdir . 'content.xml');
            $result = @$xslt->process($dom, array(
                'metaFileURL' => $tmpdir . 'meta.xml',
                'stylesFileURL' => $tmpdir . 'styles.xml',
                'disableJava' => true)
            );
            $result = $xslt->result_dump_mem($result);
        } else {
            // Use XSLT
            $xslt = xslt_create();
            $result = @xslt_process($xslt, $tmpdir . 'content.xml', $xsl_file, null, null, array(
                'metaFileURL' => $tmpdir . 'meta.xml',
                'stylesFileURL' => $tmpdir . 'styles.xml',
                'disableJava' => true)
            );
            if (!$result) {
                $result = xslt_error($xslt);
            }
            xslt_free($xslt);
        }

        return array(
            'data' => $result,
            'type' => 'text/html; charset=UTF-8'
        );
    }
}
