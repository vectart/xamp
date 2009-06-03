<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE xml SYSTEM 'entities.dtd'>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
<xsl:output method="xml" 
		version="1.0" standalone="yes" omit-xml-declaration="yes" 
		encoding="utf-8" media-type="text/html" indent="no"
		doctype-public="-//W3C//DTD XHTML 1.0 Strict//EN" doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd" />
	
<xsl:template match="/">
	<xsl:variable name="params" select="'?Hello=World&amp;another var=demo value&amp;entity=&copy;'" />
	<html>
		<head>
			<title>Welcome to the XAMP</title>
		</head>
		<body>
			<h1>XAMP</h1>
			<p>For example, GET params:</p>
			<xsl:choose>
				<xsl:when test="page/get/*">
					<ul>
						<xsl:for-each select="page/get/*">
							<li><xsl:value-of select="name()" /> - <xsl:value-of select="." /></li>
						</xsl:for-each>
					</ul>
				</xsl:when>
				<xsl:otherwise>
					are empty, <a href="{$params}">click for demo</a>
				</xsl:otherwise>
			</xsl:choose>
			<h2>XML source</h2>
			<p>
				<a href="{$params}&amp;xml=1">View generated XML with GET params</a><br />
				<small>* just add <em>xml=1</em></small>
			</p>
			<h2>Next step</h2>
			<p>Edit <b>controller/config.php</b> for complete setup</p>
		</body>
	</html>
</xsl:template>


</xsl:stylesheet>
