<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE xml SYSTEM 'entities.dtd'>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
<xsl:output method="xml" 
		version="1.0" standalone="yes" omit-xml-declaration="yes" 
		encoding="utf-8" media-type="text/html" indent="no"
		doctype-public="-//W3C//DTD XHTML 1.0 Strict//EN" doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd" />

<xsl:key name="tags" match="tag[description]" use="name" />
<xsl:key name="attributes" match="attribute[description]" use="name" />
<xsl:variable name="currentTags" select="/page/documentation/plugin[name = /page/get/plugin]/tag[description]" />
<xsl:variable name="currentAttributes" select="/page/documentation/plugin[name = /page/get/plugin]//attribute[description]" />

<xsl:template match="/page">
	<html>
		<head>
			<title>Welcome to the XAMP</title>
			<style type="text/css">
				div.code
				{
					font-family: monospace;
					line-height: 140%;
				}
				div.code div
				{
					padding-left: 10px;
				}
			</style>
		</head>
		<body>
			<h1>XAMP</h1>
			<div style="float: right; width: 75%">
				<xsl:apply-templates select="documentation/plugin[name = /page/get/plugin]" />
			</div>
			<div style="float: left; width: 24%">
				<xsl:apply-templates select="documentation" />
			</div>
		</body>
	</html>
</xsl:template>

<xsl:template match="documentation">
	<h2>Документация</h2>
	<ul>
		<xsl:apply-templates select="plugin" mode="list" />
	</ul>
</xsl:template>

<xsl:template match="plugin">
	<h3><xsl:value-of select="name" /></h3>
	<p>
		<small>
			<xsl:value-of select="file" />
		</small><br />
		<xsl:value-of select="description" />
	</p>
	<hr />
	<div style="float: left; width: 29%">
		<xsl:if test="$currentTags">
			<strong>Теги:</strong>
			<ul>
				<xsl:apply-templates select="$currentTags" mode="list">
					<xsl:sort select="name" />
				</xsl:apply-templates>
			</ul>
		</xsl:if>
		<xsl:if test="$currentAttributes">
			<strong>Аттрибуты:</strong>
			<ul>
				<xsl:apply-templates select="$currentAttributes" mode="list">
					<xsl:sort select="name" />
				</xsl:apply-templates>
			</ul>
		</xsl:if>
	</div>
	<div style="float: right; width: 70%">
		<xsl:apply-templates select="$currentTags">
			<xsl:sort select="name" />
		</xsl:apply-templates>
		<xsl:apply-templates select="$currentAttributes">
			<xsl:sort select="name" />
		</xsl:apply-templates>
	</div>
</xsl:template>

<xsl:template match="tag|attribute">
	<xsl:param name="name" select="name" />
	<h4>
		<xsl:if test="name() = 'attribute'">@</xsl:if>
		<xsl:if test="name() = 'tag'">&lt;</xsl:if>
		<xsl:value-of select="$name" />
		<xsl:if test="name() = 'tag'"> /&gt;</xsl:if>&nbsp;<a href="#{name()}_{$name}" name="{name()}_{$name}">#</a><br />
		<small><xsl:value-of select="title" /></small>
	</h4>
	<xsl:apply-templates select="description" />
	<xsl:apply-templates select="feature" />
	<xsl:apply-templates select="example" />
	<xsl:if test="attribute">
		<em>Возможные аттрибуты:</em>
		<xsl:for-each select="attribute">
			<xsl:text> @</xsl:text>
			<a>
				<xsl:apply-templates select="." mode="href" />
				<xsl:value-of select="name" />
			</a>
			<xsl:if test="position() != last()">
				<xsl:text>,</xsl:text>
			</xsl:if>
		</xsl:for-each>
	</xsl:if>
	<xsl:variable name="tags" select="ancestor::documentation/plugin/tag[attribute/name = $name]" />
	<xsl:if test="name() = 'attribute' and $tags">
		<em>Возможные теги-родители:</em>
		<xsl:for-each select="$tags">
			<xsl:text> </xsl:text>
			<xsl:text>&lt;</xsl:text>
			<a>
				<xsl:apply-templates select="." mode="href" />
				<xsl:value-of select="name" />
			</a>
			<xsl:text> /&gt;</xsl:text>
			<xsl:if test="position() != last()">
				<xsl:text>,</xsl:text>
			</xsl:if>
		</xsl:for-each>
	</xsl:if>
	<br /><br /><br />
</xsl:template>

<xsl:template match="description">
	<p>
		<xsl:apply-templates />
	</p>
</xsl:template>
<xsl:template match="description//tag|feature//tag">
	<em>
		тег <xsl:choose>
			<xsl:when test="key('tags', string(.))">
				<a>
					<xsl:apply-templates select="." mode="href" />
					<xsl:apply-templates />
				</a>
			</xsl:when>
			<xsl:otherwise>
				<xsl:apply-templates />
			</xsl:otherwise>
		</xsl:choose>
	</em>
</xsl:template>
<xsl:template match="description//attribute|feature//attribute">
	<em>
		аттрибут @<xsl:choose>
			<xsl:when test="key('attributes', string(.))">
				<a>
					<xsl:apply-templates select="." mode="href" />
					<xsl:apply-templates />
				</a>
			</xsl:when>
			<xsl:otherwise>
				<xsl:apply-templates />
			</xsl:otherwise>
		</xsl:choose>
	</em>
</xsl:template>
<xsl:template match="description//var|feature//var">
	<em>
		переменная <xsl:apply-templates />
	</em>
</xsl:template>

<xsl:template match="tag|attribute" mode="href">
	<xsl:param name="key" select="concat(name(),'s')" />
	<xsl:param name="name">
		<xsl:choose>
			<xsl:when test="name">
				<xsl:value-of select="name" />
			</xsl:when>
			<xsl:otherwise>
				<xsl:value-of select="." />
			</xsl:otherwise>
		</xsl:choose>
	</xsl:param>
	<xsl:for-each select="key($key, $name)[1]">
	<xsl:attribute name="href">
		<xsl:if test="not(ancestor::plugin/name = /page/get/plugin)">
			<xsl:text>?plugin=</xsl:text>
			<xsl:value-of select="ancestor::plugin/name" />
		</xsl:if>
		<xsl:text>#</xsl:text>
		<xsl:value-of select="name()" />
		<xsl:text>_</xsl:text>
		<xsl:value-of select="name" />
	</xsl:attribute>
	</xsl:for-each>
</xsl:template>

<xsl:template match="feature">
	<p style="margin-left: 20px">
		<em>Спецэффект:</em><br />
		<xsl:apply-templates />
	</p>
</xsl:template>

<xsl:template match="example">
	<div style="padding: 10px; background: #F5F5F5">
		<p><em>Пример:</em></p>
		<xsl:apply-templates select="code" mode="highlight" />
	</div>
</xsl:template>

<xsl:template match="*" mode="highlight">
	<div>
	<xsl:text>&lt;</xsl:text>
	<xsl:value-of select="name()" />
	<xsl:apply-templates select="@*" mode="highlight" />
	<xsl:choose>
		<xsl:when test="*">
			<xsl:text>&gt;</xsl:text>
			<xsl:apply-templates select="*" mode="highlight"/>
			<xsl:text>&lt;/</xsl:text>
			<xsl:value-of select="name()" />
			<xsl:text>&gt;</xsl:text>
		</xsl:when>
		<xsl:otherwise>
			<xsl:text> /&gt;</xsl:text>
		</xsl:otherwise>
	</xsl:choose>
	</div>
</xsl:template>
<xsl:template match="@*" mode="highlight">
	<xsl:text> </xsl:text>
	<xsl:value-of select="name()" />
	<xsl:text>=&quot;</xsl:text>
	<xsl:value-of select="." />
	<xsl:text>&quot;</xsl:text>
</xsl:template>

<xsl:template match="code[not(ancestor::code)]" mode="highlight">
	<div class="code">
		<xsl:apply-templates mode="highlight" />
		<p>
			<xsl:apply-templates select="following-sibling::description" />
		</p>
	</div>
</xsl:template>

<xsl:template match="tag|attribute" mode="list">
	<li>
		<xsl:if test="name() = 'attribute'">@</xsl:if>
		<a href="#{name()}_{name}">
			<xsl:value-of select="name" />
		</a>
		<xsl:if test="title">
			<br />
			<small>
				<xsl:value-of select="title" />
			</small>
		</xsl:if>
	</li>
</xsl:template>

<xsl:template match="plugin" mode="list">
	<li>
		<a href="?plugin={name}">
			<xsl:value-of select="name" />
		</a><br />
		<small>
			<xsl:value-of select="file" />
		</small><br />
		<xsl:value-of select="description" />
	</li>
</xsl:template>


</xsl:stylesheet>
