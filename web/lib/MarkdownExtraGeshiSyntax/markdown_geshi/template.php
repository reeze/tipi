<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN"
  "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
	<meta http-equiv="Content-type" content="text/html; charset=utf-8">
	<title><?php echo htmlentities(trim($title)) ?></title>
	<style type="text/css" media="screen">
	/* <![CDATA[ */
		/* base styling */
		body {
			margin: 1em;
			padding: 0;

			color: #000000;
			background: #FFFFFF;
			font: 13px "Trebuchet MS", Helvetica, sans-serif;
		}

		h1 {
			font-weight: bolder;
		}

		h4 {
			font-style: italic;
			margin-top: 1ex;
			margin-bottom: 1ex;
		}

		blockquote {
			font-style: italic;
			margin: 1em 25px;
			padding-left: 1em;
		}

		code, pre {
			font-size: 95%;
			font-family: "LuxiMono", "Bitstream Vera Sans Mono", "Monaco", "Courier New", monospace;
			word-wrap: break-word;
			white-space: pre;
			white-space: pre-wrap;
			white-space: -moz-pre-wrap;
			white-space: -o-pre-wrap;
		}

		pre {
			padding: 10px 10px 10px 20px;
		}

		.pro_table {
			font-size: 12px;
		}
		.pro_table th {
			font-weight: bold;
			padding: 4px 8px 4px 8px;
			text-align: left;
		}
		.pro_table td {
			padding: 8px;
			vertical-align: top;
		}
		.pro_table p {
			margin: 0;
		}
		.pro_table p + p {
			margin-top: 1em;
		}

		li > p {
			margin: 1ex 0 1ex 0;
		}

		.footnote {
			vertical-align: top;
			font-size: 75%;
			font-weight: bold;
			text-decoration: none;
		}
		.footnote:before {
			content: "[";
			vertical-align: top;
			font-weight: bold;
		}
		.footnote:after {
			content: "]";
			vertical-align: top;
			font-weight: bold;
		}

		div.footnotes {
		   padding: 1em;
		   font-size: 90%;
		}

		hr {
			background: #606060;
			color:  #606060;
			border-style: solid;
			border-color: #606060;
			border-width: 1px 0 0 0;
			margin-top: 0;
		}

		.alternate {
			background-color: #F0F0F0;
		}
		
		/* "bright" theme styling */
		.bright {
			background: #FFF;
			color: #000;
		}
		.bright a {
			color: #0D2681;
		}
		.bright h1 {
			text-shadow:  #DDDDDD 3px 3px 5px;
			color: #333;
		}
		.bright h2 {
			color: #222;
			text-shadow:  #DDDDDD 3px 3px 5px;
		}
		.bright h3 {
			color: #333;
			text-shadow:  #DDDDDD 3px 3px 5px;
		}
		.bright h4 {
			color: #666;
		}
		.bright blockquote {
			border-left: 4px solid #E6E5DD;
		}
		.bright code {
			color: #1C360C;
		}
		.bright pre {
			background-color: #f0f0f0;
			border: 1px solid #cccbba;
		}
		.bright .pro_table {
			border-top: 1px solid #919699;
			border-left: 1px solid #919699;
		}
		.bright .pro_table th {
			background: #E2E2E2;
			border-bottom: 1px solid #919699;
			border-right: 1px solid #919699;
		}
		.bright .pro_table td {
			border-bottom: 1px solid #919699;
			border-right: 1px solid #919699;
		}
		.bright .footnote {
			color: #525151;
		}
		.bright .footnote:before {
			color: #525151;
		}
		.bright .footnote:after {
			color: #525151;
		}
		.bright div.footnotes {
		   background: #F0F0F0;
		}
		.bright .alternate {
			background-color: #F0F0F0;
		}
	/* ]]> */
	</style>
</head>
<body class="bright">
<?php echo $body; ?>
</body>
</html>
