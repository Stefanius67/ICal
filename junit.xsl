<?xml version="1.0"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
<xsl:variable name="timeOK" select="0.1"/>
<xsl:variable name="timeWarning" select="0.7"/>
<xsl:variable name="timeCritical" select="1"/>
<xsl:template match="/">
  <html>
  <body>
    <xsl:for-each select="testsuites/testsuite/testsuite">
      <h2><xsl:value-of select="@name"/></h2>
      <xsl:for-each select="testsuite">
        <h3><xsl:value-of select="@name"/></h3>
        <table border="1" cellspacing="0" width="60%" style="font-family: Arial;"><tbody>
          <xsl:for-each select="testsuite">
            <tr>
              <td width="10%"></td>
              <td colspan="3" width="90%"><b><xsl:value-of select="@name"/></b></td>
            </tr>
            <xsl:for-each select="testcase">
              <tr>
                <td colspan="2" width="20%"></td>
                <td width="70%"><xsl:value-of select="@name"/></td>
                <xsl:if test="@time &lt; $timeOK">
                  <td width="10%"><xsl:value-of select="@time"/></td>
                </xsl:if>
                <xsl:if test="@time &gt;= $timeOK and @time &lt; $timeWarning">
                  <td width="10%" style="background-color: yellow"><xsl:value-of select="@time"/></td>
                </xsl:if>
                <xsl:if test="@time &gt;= $timeWarning">
                  <td width="10%" style="background-color: red; color: white"><xsl:value-of select="@time"/></td>
                </xsl:if>
              </tr>
            </xsl:for-each>
          </xsl:for-each>
          <xsl:for-each select="testcase">
            <tr>
              <td width="10%"></td>
              <td width="80%" colspan="2"><xsl:value-of select="@name"/></td>
              <xsl:if test="@time &lt; $timeOK">
                <td width="10%"><xsl:value-of select="@time"/></td>
              </xsl:if>
              <xsl:if test="@time &gt;= $timeOK and @time &lt; $timeWarning">
                <td width="10%" style="background-color: yellow"><xsl:value-of select="@time"/></td>
              </xsl:if>
              <xsl:if test="@time &gt;= $timeWarning">
                <td width="10%" style="background-color: red; color: white"><xsl:value-of select="@time"/></td>
              </xsl:if>
            </tr>
          </xsl:for-each>
        </tbody></table>
      </xsl:for-each>
    </xsl:for-each>
  </body>
  </html>
</xsl:template>
</xsl:stylesheet>
