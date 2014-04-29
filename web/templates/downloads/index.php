<h1>下载</h1>

<div>
<p>
TIPI项目，主要包括: 深入理解PHP，以及相关的项目,本站所有的PHP源代码以及网站的一些设计素材。
</p>
<p>
深入理解PHP内核这本书目前提供PDF、CHM格式的下载，将来会提供Epub格式的版本，项目还处在初期，
书的内容还没有完全写完，这也是一个长久的过程。不过我们每次更新：比如写完一章都会发布新的版本，同时提供各种格式的下载。
</p>

<p>目前提供PDF以及CHM格式版本的下载，当前版本为：<span class="current-version"><?php echo TIPI::getVersion(); ?></span>：</p>
<div id="download-list" class="clearfix">
	<div style='padding-left: 100px;' class="fl"><a target='_blank' href="<?php echo url_for("https://github.com/reeze/tipi/blob/master/web/releases/" . TIPI::getVersion() . ".pdf?raw=true");?>">
	<img src="<?php echo url_for("/images/icon_pdf.png"); ?>" /></a></div>
	<div style='padding-left: 100px;' class="fl"><a target='_blank' href="<?php echo url_for("https://github.com/reeze/tipi/blob/master/web/releases/" . TIPI::getVersion() . ".chm?raw=true");?>">
	<img src="<?php echo url_for("/images/icon_chm.png"); ?>" /></a></div>
</div>

<p>所有历史版本下载见: <a href="https://github.com/reeze/tipi/tree/master/web/releases">Github 历史页面</a></p>

<p>
本站所有的内容都托管在github上,我们目前推荐从： <a href="http://github.com/reeze/tipi">http://github.com/reeze/tipi</a>下载。
从那里可以轻松的下载所有的内容，你也可以<a href="<?php echo url_for("/feed/");?>">订阅我们的更新</a>，我们每完成一小节都会在这里更新，
或者我们发布了新版本的移动版本书籍或者有其他消息也会通过RSS输出，所以订阅我们可以随时和我们保持联系，
也可以点击进入<a href="<?php echo url_for("/");?>">首页</a>， 那里有我们的各种联系方式，比如在twitter上fo我们。
</p>
</div>

