<?
$view_ext=array("php","db","html","js","css");//검색제외항목 확장자
function h($var){
	if (is_array($var))return array_map('h', $var);
	else return htmlspecialchars($var, ENT_QUOTES, 'UTF-8');
}
function getFileProperty($file){
	$arr=array();
	$point=mb_strripos($file,"/",0,"utf-8");// 마지막 / 요소 찾음
	$len=mb_strlen($file,'UTF-8');
	if(!$point)$point=-1;//경로명이 아닐경우,공백으로 대입
	$arr["dir"]=mb_substr($file,0,$point+1,'utf-8');//디렉토리
	$arr["file"]=mb_substr($file,$point+1,$len,'utf-8');//파일전채명
	$point=mb_strripos($file,".",$point,"utf-8");//파일 확장자
	if($point){//파일 포인터가 존재하면서, 파일인 요소
		$arr["ext"]=mb_substr($file,$point+1,$len-$point,'utf-8');
		$arr["name"]=mb_substr($arr["file"],0,$point,'utf-8');
		//$ext=mb_substr($file,$point+1,$len-$point,'utf-8');
		$arr["type"]=getTypeExt($arr[ext]);
	}else{//확장자가 없네?/파일이 아니네?
		$arr["ext"]=is_file(__DIR__ ."/".$dir);//확장자
		$arr["name"]=$arr["file"];
		$arr["type"]="";//파일 타입
	}
	return $arr;
}
function getTypeExt($ext){
	$a=getTypeExtN($ext);
	if($a)
		return $a."/".$ext;
	else return "application/octet-stream";
}
function getTypeExtN($ext){
	$ext=strtolower($ext);
	switch($ext){
		case "jpg":case "png":case "jpeg":case "bmp":case "ico":case "svg":case "gif":
			return "image";
		case "mp3":case "flac":
			return "audio";
		case "mp4":case "mkv":
			return "video";
		case "smi":
			return "subtitle";
		default:
			return false;
	}
}
function getIMG($type){//이미지
	switch($type){
		case "folder":	return "http://549.ipdisk.co.kr:9999/image/exp_folder.gif";
		case "zip":		return "http://549.ipdisk.co.kr:9999/image/exp_archive.gif";
		case "music":case "audio":return "http://549.ipdisk.co.kr:9999/image/exp_music.gif";
		case "video":	return "http://549.ipdisk.co.kr:9999/image/exp_movie.gif";
		case "image":		return "http://549.ipdisk.co.kr:9999/image/exp_pic.gif";
		case "document":case "page":return "http://549.ipdisk.co.kr:9999/image/exp_office.gif";
		case "up":	return "http://549.ipdisk.co.kr:9999/image/icon_upper_black.gif";
		case "exit":return "http://549.ipdisk.co.kr:9999/image/icon_bt_cencle.gif";
		case "dir":return "http://549.ipdisk.co.kr:9999/image/exp_icon_folder_black.gif";
		case "file":default:	return "http://549.ipdisk.co.kr:9999/image/exp_etc.gif";
	}
}
function fopen_utf8($filename){
	$encoding='';
	$handle = fopen($filename, 'r');
	$bom = fread($handle, 2);
	//  fclose($handle);
	rewind($handle);
	if($bom === chr(0xff).chr(0xfe)  || $bom === chr(0xfe).chr(0xff)){
			// UTF16 Byte Order Mark present
			$encoding = 'UTF-16';
	} else {
		$file_sample = fread($handle, 1000) + 'e'; //read first 1000 bytes
		// + e is a workaround for mb_string bug
		rewind($handle);
		$encoding = mb_detect_encoding($file_sample , 'UTF-8, UTF-7, ASCII, EUC-JP,SJIS, eucJP-win, SJIS-win, JIS, ISO-2022-JP');
	}
	if ($encoding)stream_filter_append($handle, 'convert.iconv.'.$encoding.'/UTF-8');
	return ($handle);
}
function getSMI($f){
	$fp = fopen_utf8($f);
	if(!$fp){
		echo "못읽음";
		die;
	}
	$index = -1;	//행의 개수
	$col_time = 0;	//시간을 담는 행
	$col_text = 1;	//문자를 담는 행
	$text = "";
	while(!feof($fp)){
		$line=@fgets($fp);
		if(stristr($line , "<Sync")){
			$index++;
			$text = "";
			$start = strpos($line , "=")+1;
			$end = strpos($line , ">");
			$time = substr($line , $start , $end-$start);
			if(strchr($time , " "))
				$time = substr($time ,0, strpos($time , " "));
			$smi[$index][$col_time] = $time;
			$text = strstr($line , ">");
			$text = str_replace(array("\r\n","\r","\n"),'',$text);//개행문자 제거
			$text = preg_replace("/<p[^>]*>/i",'', $text);
			$smi[$index][$col_text]=substr($text , 1 , strlen($text));
		}else{
			$line=str_replace(array("\r\n","\r","\n"),'',$line);
			$smi[$index][$col_text].=$line;
		}
	}
	return json_encode($smi,JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
}
if(isset($_GET[dir])){//디렉토리 리턴	//=================================분기점
	$dir=getcwd().'/'.h($_GET[dir]);
	if (mb_strpos($dir, '.')||!is_dir($dir)){
		json_encode(array(getFileProperty(".")),JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
		exit;
	}
	$dirs = scandir($dir);
	$list=array();
	header("Contnet-Type: application/json; charset=UTF-8");
	header("X-Contnet-Type-Options: mosniff");
	foreach($dirs as $file){
		$data=getFileProperty($file);
		if(in_array($data["type"],$view_ext)||$file==="."||$file==="..")
			continue;//열외 항목들
		$list[]=$data;
	}
	echo json_encode($list,JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
	exit;
}else if(isset($_GET[file])){
	ob_clean();//출력 버퍼 클린
	flush();//시스템 버퍼 플러시(?)
	$dir=getcwd().'/'.h($_GET[file]);//file 인자에 해당하는 위치를 가져옵니다.
	$file=getFileProperty($dir);//파일 정보를 가져옵니다.
	if($file[ext]===false||in_array($file[ext],$view_ext)){
		//폴더이거나, 금지 확장자이면, 엑세스를 거부합니다.
		die("폴더/금지파일를(을) 엑세스?");
		exit;
	}
	header("Pragma: private");
	header("Expires: 0");
	header("Content-Type: $file[type]");
	header("Content-Disposition: attachment; filename=\"$file[name]\"");
	header("Content-Transfer-Encoding: binary");
	$filesize = filesize($dir);
	header("Content-Length: $filesize");
	//파일에 헤더를 전송합니다.
	readfile($dir);//파일을 읽어 버퍼에 출력합니다.
	ob_flush();//버퍼를 플러시해줍니다
	exit;//공용스크립트가 읽히지 않도록 해 줍니다.
}else if(isset($_GET[smi])){
	$dir=getcwd().'/'.h($_GET[smi]);
	$file=getFileProperty($dir);
	if($file[ext]===false||getTypeExtN($file[ext])!=="subtitle"){
		die("폴더/금지파일를(을) 엑세스?");
		exit;
	}
	header("Contnet-Type: application/json; charset=UTF-8");
	header("X-Contnet-Type-Options: mosniff");
	echo getSMI($dir);
	exit;
}else if(isset($_GET[window])){//iframe에 전송할 내용	//=================================분기점
	?>
	<meta http-equiv="Pragma" content="no-cache">
	<meta http-equiv="Cache-Control" content="no-cache">
	<meta http-equiv="expires" content="0">
	<?
	$window=h($_GET[window]);
	echo '<img src='.getIMG("dir").'><font size=3>'.$window .'</font>';//헤더
	echo '<img id=exit src='.getIMG("exit").' onclick="outpage()"><br>';//윈도우 종료
	$file=getFileProperty(__DIR__ ."/". $window);
	$type=getTypeExtN($file[ext]);
	$index=0;
	switch($type){
		case "audio":case "video":case "image":
			$root = scandir($file[dir]);
			$list = array();
			$i=0;
			foreach($root as $f){
				$option=getFileProperty($f);
				$ftype=getTypeExtN($option[ext]);
				if($ftype===$type){
					$option["sub"]=false;
					$list[]=$option;
					if($option[file]===$file[file])//파일 비교
						$index=$i;
					$i++;
				}else if($ftype==="subtitle")
					$list[]=$option;
			}
			//오디오 생성
?><<?=$type?> id=player src="./?file=<?=$window?>" onended="repeat.call(this)" controls autoplay width="100%"></<?=$type?>>
<table>
	<tbody><?
	foreach($list as $f){
		if(getTypeExtN($f[ext])===$type){//일치할경우 출력
			?><tr onclick="play.call(this)"><?
				echo "<td><img src='".getIMG(getTypeExtN($f[ext]))."'/></td>";
				echo "<td class=list-text-element >".$f[name]."</td>";
				if(in_array(getTypeExtN($f[ext]),array("video","audio"))){//비디오/오디오 처리
					for($i=0;$i<count($list);$i++)
						if($list[$i][name]===$f[name]/*&&getTypeExtN($list[$i][ext])==="subtitle"*/){
							$list[$i][sub]=true;
							break;
						}
					echo "<td>Sub".($list[$i][sub]?"OK":"NO")."</td>";
				}
			?></tr><?
		}
	}
	?></tbody>
</table><script>
	var index=<?=$index?>,list=<?echo json_encode($list,JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);?>;
	function repeat(){
		var max=list.length;
		index++;
		if(index>=max)index=0;
		this.pause();
		this.src="?file="+parent.getRoot()+list[index].file;
		this.play();
	}
	function play(){
		var index=this.indexOf();
		window.location.href ="?window="+parent.getRoot()+list[index].file;
	}
	function outpage(){
		window.location.href="about:blank";
		var ele=parent.document.getElementById("page");
		parent.document.getElementById("content").style.display="block";
		ele.style.width="1px";
		ele.style.height="1px";
	}
	window.onload=function(){//자막찾기 프로젝트
		var sub_index=0;
		for(var i=0;i<list.length;i++){
			if(getTYPE(list[i].ext)!="subtitle")continue;
			if(list[i].name==list[index].name)sub_index=i;
		}
		getJ("?smi="+parent.getRoot()+list[sub_index].file,function(data){
			console.log(data);
		});
	}
	</script><?
	}
}else{//첫화면(리스트)
	?>
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Cache-Control" content="no-cache">
<meta http-equiv="expires" content="0">
<iframe id=page></iframe>
<nav></nav>
<div id=title></div>
<table id=content>
</table>
<script>
function getRoot(){
	var out="",l=stack.all();
	for(var a=0;a<l.length;a++)
		out+=l[a]+"/";
	return out;
}
function getCmpPos(obj){
	var rect=obj.getBoundingClientRect(),height;
	if(rect.height)
		height=rect.height;
	else height=rect.bottom-rect.top;
	return [rect.left,rect.top+height+10];
}
function dirs(arr){
	var a=this.getElementById("content"),b=this.createElement("tbody");
	a.clear();
	function addNode(element){
		var c=b.createElement("tr"),d;
		d=c.createElement("td");
		if(!element.ext&&element.ext!=null)
			d.createElement("img").src=getIMG("folder");
		else d.createElement("img").src=getIMG(getTYPE(element.ext));
		d=c.createElement("td");
		d.className="list-text-element";
		d.innerHTML=element.name;
		if(["img"].indexOf(getTYPE(element.ext))+1){//마우스 드롭 이벤트
			//이미지 드롭 이벤트를 기입해줍니다.
			d.data=element;//드롭이벤트시, 내 항목이 뭔지 알아야 하기 때문에, 저장한다.
			d.addEventListener("mouseover",function(){
				var d=this.data;//데이터 가져옴
				switch(getTYPE(d.ext)){//여러개 처리할려고 했기에..
					case "img"://항목이 이미지일경우 처리
						var loc=getCmpPos(this);//항목의 위치를 가져온다.
						var left=loc[0]+"px",top=loc[1]+"px";//좌표계산
						var div=document.body.createElement("popup"),img=div.createElement("img");//팝업항목과, 이미지 항목을 같이 생성.
						img.src="?file="+getRoot()+d.file;//이미지 지정(getRoot[파일 경로])
						img.style.width="300px";//지정크기
						img.style.height="auto";//세로는 마음대로
						div.id="popup";//아이디
						div.setAttribute("style","left:"+left+";top:"+top+";position:fixed");//위치 지정//디스플레이 고정
						break;
					default:break;
				}			
			},false);
			d.addEventListener("mouseout",function(){
				var d=this.data;//데이터 가져옴
				switch(getTYPE(d.ext)){
					case "img"://이미지 일 경우
						var popup=document.getElementById("popup");//팝업노드를 찾아서
						if(popup)//존재할 경우
							popup.parentNode.removeChild(popup);//제거
						return false;
					default://기타처리
						break;
				}
			},false);
		}
		d.onclick=function(){
			index = this.parentNode.indexOf();
			if(!listarr[index].ext&&listarr[index].ext!=null){//디렉토리
				stack.push(listarr[index].file);
				title.textContent=getRoot();
				b.clear();
				var tmp = b.createElement("tr"),tmp1=tmp.createElement("td");
				tmp1.innerHTML="불러오는중...";
				getJ("?dir="+getRoot(),dirs);
			}else{//파일
				switch(getTYPE(listarr[index].ext)){
					case "music":case "video":case "img":
						page.style.width="100%";
						page.style.height="90%";
						page.src="?window="+getRoot()+listarr[index].file;
						content.style.display="none";
						break;
					case "up":
						console.log("상위");
						stack.pop();
						title.textContent=getRoot();
						getJ("?dir="+getRoot(),dirs);
						break;
				}
			}
		}
	}
	
	for(var e=arr.length-1;e>=0;e--){//역탐색
		var obj=arr[e];
		if(obj.name=="."||obj.name==".."||obj.ext=="php")
			arr.splice(e,1);
	}
	listarr=arr;
	listarr.sort(function(a,b){
		var ext=getTYPE(a.ext);
		if(!ext||ext==="folder")return -1;
		function low(a,b){
			if(!a||!b)
				return !b;
			var c=a.toLowerCase(),d=b.toLowerCase();
			return +(c > d) || +(c === d) - 1;
		}
		if(a.ext===b.ext)
			return low(a.name,b.ext);
		return low(a.ext,b.ext);
	});
	if(stack.length())listarr.unshift({dir:".",name:"상위",root:"상위",ext:"up"});
	for(var e=0;e<arr.length;e++)addNode(arr[e]);
	a.appendChild(b);
}

window.onload=function(){
	stack=new Stack();
	page=document.getElementById("page");
	content=document.getElementById("content");
	title=document.getElementById("title");
	getJ("?dir",dirs);
}
</script>
	<?
}
?>
<style>
#page{
	width:1;
	height:1;
	padding:0;
	margin:0;
	border: 0;
}
tr:nth-child(2n-1) td{
	background:transparent;
	background-color:#F7F7F7;
}
tr:nth-child(2n) td{
	background:transparent;
	background-color:#FFFFFF;
}
.popup{
	position:fixed;
}
</style>
<script>
//스텍
function Stack(){
	this.data = [];
	this.top = 0;
	this.push=function(element){this.data[this.top++]=element;}
	this.pop=function(){if(this.top)return this.data[--this.top];else return 0;}
	this.peek=function(){return this.data[this.top-1];}
	this.length=function(){return this.top;}
	this.clear=function(){this.top = 0;this.data.length=0;}
	this.all=function(a,l){l=[];for(a=0;a<this.top;a++)l.push(this.data[a]);return l;}
}
Element.prototype.clear=function(){
	while (this.firstChild)
		this.removeChild(this.firstChild);
}
Element.prototype.indexOf=function(){
	var c=this.parentNode.childNodes,i=0;
	for(;i<c.length;i++)if(c[i]==this)return i;
	return -1;
};
Element.prototype.createElement=function(ele){
	var e = document.createElement(ele);
	this.appendChild(e);
	return e;
}
function getJ(url,callback){
	var xmlhttp = new XMLHttpRequest();
	xmlhttp.onreadystatechange = function() {
		if (this.readyState == 4 && this.status == 200) {
			try{
				callback.call(document,JSON.parse(this.responseText));
			}catch(e){
				console.log(e);
				callback.call(document,false,this.responseText);
			}
		}
	};
	xmlhttp.open("GET", url, true);
	xmlhttp.send();
}
function getIMG(type){
	switch(type){
		case "folder":	return "<?=getIMG("folder")?>";
		case "zip":		return "<?=getIMG("zip")?>";
		case "music":	return "<?=getIMG("music")?>";
		case "video":	return "<?=getIMG("video")?>";
		case "img":		return "<?=getIMG("img")?>";
		case "document":case "page":case "subtitle":return "<?=getIMG("document")?>";
		case "file":	return "<?=getIMG("file")?>";
		case "up":	return "<?=getIMG("up")?>";
	}
}

function getTYPE(type){
	switch(type){
		case "folder":case false:
			return "folder";
		case "zip":
			return "zip";
		case "up":
			return "up";
		case "mp3":case "flac":
			return "music";
		case "jpg":case "png":case "jpeg":case "bmp":case "ico":case "svg":case "gif":
			return "img";
		case "mp4":case "mkv":
			return "video";
		case "hwp":case "pdf":
			return "document";
		case "smi":
			return "subtitle";
		case "html":case "htm":case "php":
			return "page";//열람가능 페이지
		default:return "file";//열람불가
	}
}
</script>
