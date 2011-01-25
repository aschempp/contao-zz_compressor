-- **********************************************************
-- *                                                        *
-- * IMPORTANT NOTE                                         *
-- *                                                        *
-- * Do not import this file manually but use the TYPOlight *
-- * install tool to create and maintain database tables!   *
-- *                                                        *
-- **********************************************************

-- 
-- Table `tl_layout`
-- 

CREATE TABLE `tl_layout` (
  `compressStyleSheets` char(1) NOT NULL default '',
  `compressJavascript` char(1) NOT NULL default '',
  `compressTemplate` char(1) NOT NULL default '',
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

