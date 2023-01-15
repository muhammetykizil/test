<?
error_reporting(1);
@ob_start();
session_start();

include "sistem/ayarlar.php";
include "sistem/veritabani/baglanti.php";
include "sistem/fonksiyonlar.php";
include "sistem/listeler.php";

if(GirisVarmi())
{
	class MySQLException extends Exception {}
	try	{
		
	if(!isset($_GET["EsyaId"]) or !isset($_GET["Adet"]))
		throw new Exception("Satın alma gerçekleştirilemedi.");
	
	$ItemId = intval($_GET["EsyaId"]);
	$Adet = intval($_GET["Adet"]);
	
	if($Adet != 1 and $Adet != 5 and $Adet != 10 and $Adet != 20 and $Adet != 50)
		throw new Exception("Adet sayısız geçersiz.<br>Satın alma işlemi iptal edildi.");
	
	$Tarih = date("Y-m-d H:i:s");
							
	$Query = mysql_query("SELECT * FROM ".MYSQL_DB.".is_items WHERE Id = '$ItemId'");
	
	if(mysql_error())
		throw new Exception("Eşya bulunamadı.");
	
	$Say = mysql_num_rows($Query);
	
	if($Say != 1)
		throw new Exception("Eşya bulunamadı.");
	
	$VeriCek = mysql_fetch_array($Query);
	
	if($VeriCek["OdemeTuru"] == "EP")
		$OdemeTuru = EP_TIPI;
	elseif($VeriCek["OdemeTuru"] == "EM")
		$OdemeTuru = EM_TIPI;
	else
		throw new Exception("Eşya ödeme tipi ile ilgili bir hata ile karşılaşıldı.");		
	
	
	if($VeriCek["Kampanya"] > 0)
		$Ucret = $VeriCek["Ucret"] - ($VeriCek["Ucret"] / 100) * $VeriCek["Kampanya"];
	else
		$Ucret = $VeriCek["Ucret"];
	
	$ToplamUcret = $Adet * $Ucret;
	
	$HesapQuery = mysql_query("SELECT ".$OdemeTuru." FROM ".MYSQL_ACCOUNT.".account WHERE id = '".$_SESSION["HesapId"]."'");
	$HesapCek = mysql_fetch_array($HesapQuery);
	
	$MevcutBakiye = $HesapCek[$OdemeTuru];							
	
	if($MevcutBakiye < $ToplamUcret)
		throw new Exception("Yetersiz bakiye.");
	
	if($VeriCek[OdemeTuru] == "EP")
		$_SESSION[Ep] = $MevcutBakiye - $ToplamUcret;
	elseif($VeriCek[OdemeTuru] == "EM")
		$_SESSION[Em] = $MevcutBakiye - $ToplamUcret;
	
	
	if($VeriCek["Sure"] > 0)
	{
		if(time() > strtotime($VeriCek["Sure"]))
			throw new Exception("Eşyanın satılma süresi dolduğu için , satın alma işlemi gerçekleşemedi.");
	}
	
	if($VeriCek["Stok"] > 0)
	{
		if($VeriCek["Stok"] < 1)
			throw new Exception("Üzgünüm bu eşyada stok tükenmiş.");
		
		if($Adet > $VeriCek["Stok"])
			throw new Exception("Alacağınız adet sayısı mevcut stoktan fazla olduğu için satın alma gerçekleşmedi.");
		
		mysql_query("UPDATE ".MYSQL_DB.".is_items SET Stok = Stok - $Adet WHERE Id = '$ItemId'");
		// echo("UPDATE ".MYSQL_DB.".is_items SET Stok = Stok - $Adet WHERE Id = '$ItemId' <br>");
	}
	
	if($VeriCek[Paket] == 1)
	{
		$PaketQuery = mysql_query("SELECT * FROM ".MYSQL_DB.".is_paket WHERE PaketId = '$ItemId'");
		$PaketSay = mysql_num_rows($PaketQuery);
		
		if($PaketSay < 1)
			throw new Exception("Bu paketin içerisinde eşya bulunamadı.");
		
		$ToplamPosSay = $PaketSay * $Adet;
		
		$PosQuery = mysql_query("SELECT pos FROM ".MYSQL_PLAYER.".item WHERE owner_id = '".$_SESSION["HesapId"]."' AND window = 'MALL'");
		$PosSay = mysql_num_rows($PosQuery);
		
		if(($PosSay + $ToplamPosSay) > 45)
			throw new Exception("Nesne deponuzda yeterli alan bulunmuyor.");
		
		mysql_query("UPDATE ".MYSQL_ACCOUNT.".account SET ".$OdemeTuru." = ".$OdemeTuru." - '$ToplamUcret' WHERE id = '".$_SESSION["HesapId"]."'");
		
		while($PaketCek = mysql_fetch_array($PaketQuery))
		{
			for($Eklenen = 0;$Eklenen < $Adet;$Eklenen++)
			{
				$ItemQuery = mysql_query("SELECT socket_pct,type FROM ".MYSQL_PLAYER.".item_proto WHERE vnum = '$PaketCek[ItemVnum]'");
				// echo("SELECT socket_pct FROM ".MYSQL_PLAYER.".item_proto WHERE vnum = '$VeriCek[ItemVnum]' <br>");
				$ItemCek = mysql_fetch_array($ItemQuery);
				
				$BosTas = $ItemCek["socket_pct"];
				
				$Taslar = array(
				"socket0" => "0",
				"socket1" => "0",
				"socket2" => "0",
				"socket3" => "0",
				"socket4" => "0",
				"socket5" => "0"
				);
											
				for($i = 0,$j = $BosTas;$j > 0;$j--,$i++)
				{
					$Taslar["socket".$i] = 1;
				}
				
				$Pos = PosBul();
				
				$ItemOlustur = mysql_query("INSERT INTO ".MYSQL_PLAYER.".item (owner_id,window,pos,count,vnum) VALUES ('".$_SESSION["HesapId"]."','MALL','$Pos','$PaketCek[Adet]','$PaketCek[ItemVnum]')");
				// echo("INSERT INTO ".MYSQL_PLAYER.".item (owner_id,window,pos,count,vnum) VALUES ('".$_SESSION[HesapId]."','MALL','$Pos','$PaketCek[Adet]','$PaketCek[ItemVnum]') <br>");
				$OlusanItemId = mysql_insert_id();
				
				mysql_query("INSERT INTO ".MYSQL_DB.".is_log (HesapId,ItemVnum,OdenenEP,Tarih,ItemId,OlusanItem) VALUES('".$_SESSION["HesapId"]."','$PaketCek[ItemVnum]','$Ucret','$Tarih','$ItemId','$OlusanItemId')");
				// echo("INSERT INTO ".MYSQL_DB.".is_log (HesapId,ItemVnum,OdenenEP,Tarih,ItemId,OlusanItem) VALUES('".$_SESSION[HesapId]."','$PaketCek[ItemVnum]','$Ucret','$Tarih','$ItemId','$OlusanItemId') <br>");
				
				foreach($Taslar as $Veri => $Key)
				{
					if($Key == 1)
						mysql_query("UPDATE ".MYSQL_PLAYER.".item SET $Veri = '1' WHERE id = '$OlusanItemId'");
				}
				
				if($BosTas == 0 and (strlen($PaketCek["ItemSuresi"]) > 0))
				{
					$OlusanItemSuresi = time() + (60*$PaketCek["ItemSuresi"]);
					if($ItemCek["type"] == 16)
						mysql_query("UPDATE ".MYSQL_PLAYER.".item SET socket3 = '$PaketCek[ItemSuresi]' WHERE id = '$OlusanItemId'");
					elseif($ItemCek["type"] == 28) 
						mysql_query("UPDATE ".MYSQL_PLAYER.".item SET socket0 = '$OlusanItemSuresi' WHERE id = '$OlusanItemId'");
				}
			}
		}
	}
	else
	{
		$PosQuery = mysql_query("SELECT pos FROM ".MYSQL_PLAYER.".item WHERE owner_id = '".$_SESSION["HesapId"]."' AND window = 'MALL'");
		$PosSay = mysql_num_rows($PosQuery);
		
		if(($PosSay + $Adet) > 45)
			throw new Exception("Nesne deponuzda yeterli alan bulunmuyor.");
		
		$ItemQuery = mysql_query("SELECT socket_pct,type FROM ".MYSQL_PLAYER.".item_proto WHERE vnum = '$VeriCek[ItemVnum]'");
		// echo("SELECT socket_pct FROM ".MYSQL_PLAYER.".item_proto WHERE vnum = '$VeriCek[ItemVnum]' <br>");
		$ItemCek = mysql_fetch_array($ItemQuery);
		
		$BosTas = $ItemCek["socket_pct"];
		
		$Taslar = array(
		"socket0" => "0",
		"socket1" => "0",
		"socket2" => "0",
		"socket3" => "0",
		"socket4" => "0",
		"socket5" => "0"
		);
									
		for($i = 0,$j = $BosTas;$j > 0;$j--,$i++)
		{
			$Taslar["socket".$i] = 1;
		}
		
		mysql_query("UPDATE ".MYSQL_ACCOUNT.".account SET ".$OdemeTuru." = ".$OdemeTuru." - '$ToplamUcret' WHERE id = '".$_SESSION["HesapId"]."'");
													
		for($Eklenen = 0;$Eklenen < $Adet;$Eklenen++)
		{
			$Pos = PosBul();
			
			$ItemOlustur = mysql_query("INSERT INTO ".MYSQL_PLAYER.".item (owner_id,window,pos,count,vnum) VALUES ('".$_SESSION["HesapId"]."','MALL','$Pos','$VeriCek[Adet]','$VeriCek[ItemVnum]')");
			// echo("INSERT INTO ".MYSQL_PLAYER.".item (owner_id,window,pos,count,vnum) VALUES ('".$_SESSION[HesapId]."','MALL','$Pos','$VeriCek[Adet]','$VeriCek[ItemVnum]') <br>");
			$OlusanItemId = mysql_insert_id();
			
			mysql_query("INSERT INTO ".MYSQL_DB.".is_log (HesapId,ItemVnum,OdenenEP,Tarih,ItemId,OlusanItem) VALUES('".$_SESSION["HesapId"]."','$VeriCek[ItemVnum]','$Ucret','$Tarih','$ItemId','$OlusanItemId')");
			// echo("INSERT INTO ".MYSQL_DB.".is_log (HesapId,ItemVnum,OdenenEP,Tarih,ItemId,OlusanItem) VALUES('".$_SESSION[HesapId]."','$VeriCek[ItemVnum]','$Ucret','$Tarih','$ItemId','$OlusanItemId') <br>");
			
			foreach($Taslar as $Veri => $Key)
			{
				if($Key == 1)
					mysql_query("UPDATE ".MYSQL_PLAYER.".item SET $Veri = '1' WHERE id = '$OlusanItemId'");
			}
			
			if($BosTas == 0 and (strlen($VeriCek["ItemSuresi"]) > 0))
			{ 
				$OlusanItemSuresi = time() + (60*$VeriCek["ItemSuresi"]);
				if($ItemCek["type"] == 16)
					mysql_query("UPDATE ".MYSQL_PLAYER.".item SET socket3 = '$VeriCek[ItemSuresi]' WHERE id = '$OlusanItemId'");
				elseif($ItemCek["type"] == 28)
					mysql_query("UPDATE ".MYSQL_PLAYER.".item SET socket0 = '$OlusanItemSuresi' WHERE id = '$OlusanItemId'");
				elseif($ItemCek["type"] == 18)
					mysql_query("UPDATE ".MYSQL_PLAYER.".item SET socket0 = '$OlusanItemSuresi' WHERE id = '$OlusanItemId'");
			}
		}	
	}
	
	if($VeriCek["OdemeTuru"] == "EP")
	{
		$KazancEM = $ToplamUcret * EM_ORANI;
	
		if(strlen(EM_TIPI) > 0)
		{
			mysql_query("UPDATE ".MYSQL_ACCOUNT.".account SET ".EM_TIPI." = ".EM_TIPI." + '$KazancEM' WHERE id = '".$_SESSION["HesapId"]."'");
			// echo("UPDATE ".MYSQL_ACCOUNT.".account SET ".EM_TIPI." = ".EM_TIPI." + '$KazancEM' WHERE id = '".$_SESSION[HesapId]."' <br>");								
		}
		
		$_SESSION[Em] += $KazancEM;
	}
	
	$Log = new Log; 
	$Log->MYSQL("EsyaSatinAldi","Eşya Id : $ItemId|Adet : $Adet|Item Adi : $VeriCek[ItemAdi]|Satın Almadan Önceki Bakiye : $MevcutBakiye|Toplam Tutar : $ToplamUcret|Olusan Item Id : $OlusanItemId");
	
	?>
	<div id="confirmation">
		<div id="itemConfirmation" class="contrast-box">
			<div class="row-fluid clearfix">
				<div class="item-showcase grey-box span4">
					<div id="image" class="picture_wrapper">
						<img class="image" src="<?=$VeriCek["Resim"]?>" width="242" height="242" alt="<?=$VeriCek["ItemAdi"]?>">
					</div>
				</div>
				<div class="item-confirmation grey-box clearfix span8">
					<h3>Alışverişin için teşekkür ederiz!</h3>
					<p class="confirmed"><?=$VeriCek["ItemAdi"]?> <small>(<?=$Adet?> adet)</small></p>
					<p>Eşyanı hesabının eşya dükkanı deposunda bulursun. "Hesap yükseltme" kategorisinde ait eşyalar bir sonraki girişinde otomatik olarak etkinleşir. </p>
					
					<p class="text-success">
					<span class="block-price">
						<img class="ttip" src="//gf3.geo.gfsrv.net/cdn82/aa9089464e87d3f71036ac9ed97346.png" tooltip-content="Ejderha Markası" alt="Ejderha Markası">
					</span>
					Bu alışveriş sana <span class="end-price" id="KazanilacakEm"><?=$KazancEM?> EM</span> kazandırdı.            
				</p>
					
					<button title="Alışverişe devam" class="right  btn-default" onclick="javascript:$.fancybox.close();">Alışverişe devam</button>
				</div>
			</div>
		</div>
		<h3>Önerilerimiz</h3>   
		<div class="carousell royalSlider contentslider rsDefault visibleNearby card">		
		<?
		$Query = mysql_query("SELECT
		Count(is_log.ItemId) AS Say,
		is_log.ItemId,
		is_items.ItemVnum,
		is_items.ItemAdi,
		is_items.Resim,
		is_items.Ucret,
		is_items.Id,
		is_items.OdemeTuru,
		is_kategorien.Id as KategorimId,
		is_items.Kampanya,
		is_items.Stok,
		is_items.Sure,
		is_kategorien.KategoriAdi
		FROM
		".MYSQL_DB.".is_log
		INNER JOIN ".MYSQL_DB.".is_items ON is_log.ItemId = is_items.Id
		INNER JOIN ".MYSQL_DB.".is_kategorien ON is_items.KategoriId = is_kategorien.Id
		WHERE is_items.Id NOT LIKE '$ItemId'
		GROUP BY ItemId
		HAVING Say > 1
		ORDER BY Say DESC
		LIMIT 10
		");
		
		if(mysql_num_rows($Query)  == 0)
		{
			$Mesaj = new Mesaj();
			$Mesaj->Bilgi("En çok satılan eşya listesi boş");
		}
		else
		{						
			while($VeriCek = mysql_fetch_array($Query))
			{
				
				
				if($VeriCek["Kampanya"] > 0)
					$KampanyaFiyat = ceil(($VeriCek["Ucret"] / 100) * $VeriCek["Kampanya"]);
				else
					$KampanyaFiyat = $VeriCek["Ucret"];
				
				echo '
				<div class="span4 list-item quickbuy">
				<div class="contrast-box  item-box inner-content clearfix" >
					<div class="desc row-fluid">
						<div class="item-description">
						<p class="item-status js_currency_default" data-currency="1">
							<!--
							hd-limited-time = Süreli İtem
							hd-limited = Sınırlı Stok
							hd-discount = İndirim
							-->
							';
							
							if($VeriCek["Kampanya"] > 0)
								echo '<i class="zicon-hd-discount ttip" tooltip-content="%'.$VeriCek["Kampanya"].' İndirim"></i>';
							if($VeriCek["Stok"] > 0)
								echo '<i class="zicon-hd-limited ttip" tooltip-content="Son '.$VeriCek["Stok"].' Stok"></i>';
							if($VeriCek["Sure"] > 0)
								echo '<i class="zicon-hd-limited-time ttip" tooltip-content="Süreli Eşya"></i>';							

							echo '
						</p>
						<h4><a class="fancybox fancybox.ajax card-heading"  href="#" title="'.$VeriCek["ItemAdi"].'">'.$VeriCek["ItemAdi"].'</a></h4>
						<div id="scrollTo03847" class="item-shortdesc clearfix"  >
							<a class="item-thumb fancybox fancybox.ajax" href="#" title="">
								<img class=" item-thumb" src="'.$VeriCek["Resim"].'" alt="'.$VeriCek["ItemAdi"].'">
							</a>
                            <span class="category-link">
								<img height="15" width="15" src="//gf3.geo.gfsrv.net/cdn53/aab2cbed9df9dbcdc746b964b95d9f.png" class="icon " >
								<a href="index.php?git=Kategori&Id='.$VeriCek["KategorimId"].'" title="'.$VeriCek["KategoriAdi"].'">'.$VeriCek["KategoriAdi"].'</a>
							</span>
                            <p>
							';
							
							echo KesinAciklama($VeriCek["Id"]);
							
							echo '                       
							</p>
                           </div>
						</div>
						<div class="price_desc row-fluid    js_currency_default" data-currency="2">
							<button class="btn-default fancybox fancybox.ajax " href="Detay.php?Esya='.$VeriCek["Id"].'">Ayrıntılar &raquo;</button>
							<p class="span5 price">
								<span class="price-label">Fiyat:</span>
								<span class="block-price">
									';
									
									if($VeriCek["OdemeTuru"] == "EM")
										echo '<img class="ttip" src="//gf3.geo.gfsrv.net/cdn82/aa9089464e87d3f71036ac9ed97346.png" alt="Ejderha Markası" tooltip-content="Ejderha Markası" />';
									elseif($VeriCek["OdemeTuru"] == "EP")
										echo '<img class="ttip" src="//gf1.geo.gfsrv.net/cdn06/479d2a18c634f5772a66d11e35f9f9.png" alt="Ejderha Parası" tooltip-content="Ejderha Parası" />';
									
									echo '									
									<span class="end-price">'.$KampanyaFiyat.'</span>
								</span>
							</p>
                            <button class="span5 btn-price">
								<span class="block-price">
									';
									
									if($VeriCek["OdemeTuru"] == "EM")
										echo '<img class="ttip" src="//gf3.geo.gfsrv.net/cdn82/aa9089464e87d3f71036ac9ed97346.png" alt="Ejderha Markası" tooltip-content="Ejderha Markası" />';
									elseif($VeriCek["OdemeTuru"] == "EP")
										echo '<img class="ttip" src="//gf1.geo.gfsrv.net/cdn06/479d2a18c634f5772a66d11e35f9f9.png" alt="Ejderha Parası" tooltip-content="Ejderha Parası" />';
									
									echo '
								   <span class="end-price">'.$KampanyaFiyat.'</span>
								</span>
							</button>
							<button class="span5 btn-buy fancybox fancybox.ajax" href="SatinAl.php?EsyaId='.$VeriCek["Id"].'&Adet=1">Satın al</button>
						</div>
					</div>
				</div>        
			</div>
				';
			}
		}
		?>
		</div>
	</div>	
<?
	}
	catch(Exception $HataMesaji)
	{
		echo '
		<div id="error" class="contrast-box">
		   <div class="dark-grey-box">
				<h2><i class="icon-info left"></i>Hata</h2>
				<p>'.$HataMesaji->getMessage().'</p>
				<div class="btn_wrapper">
				</div>
		   </div>
		</div>
		';			
	}
}
else
{
?>
<div id="error" class="contrast-box">
   <div class="dark-grey-box">
		<h2><i class="icon-info left"></i>Hata</h2>
		<p>Bir hata oluştu.</p>
		<h3>Böyle bir hata oluştuğunda ne yapmak lazım?</h3>
		<ul class="clearfix">
			<li>Lütfen oyun içerisinden veya siteden giriş yaptıktan sonra tekrar girmeyi deneyin</li>
		</ul>
		<div class="btn_wrapper">
		</div>
   </div>
</div>
<?
}
?>
