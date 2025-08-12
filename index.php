<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
   // 'g-recaptcha-response' (reCAPTCHA compatibility)
   $hcaptcha_valid = false;
   if (isset($_POST['h-captcha-response'])) {
      $hcaptcha_secret = '0xFa517dE3779e41616bffAF5D79Ae39Ba222b67c8';
      $hcaptcha_response = $_POST['h-captcha-response'];
      if (function_exists('curl_exec')) {
         $ch = curl_init();
         curl_setopt_array($ch, [
            CURLOPT_URL => 'https://hcaptcha.com/siteverify',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['secret' => $hcaptcha_secret, 'response' => $hcaptcha_response],
            CURLOPT_RETURNTRANSFER => true
         ]);
         $hcaptcha = curl_exec($ch);
         curl_close($ch);
      } else {
         $hcaptcha = file_get_contents('https://hcaptcha.com/siteverify?secret=' . $hcaptcha_secret . '&response=' . $hcaptcha_response);
      }
      $hcaptcha_result = json_decode($hcaptcha);
      if ($hcaptcha_result->success == true) {
         $hcaptcha_valid = true;
         unset($_POST['h-captcha-response']);
      }
   }
   if (!$hcaptcha_valid) {
      header('Location: ./form_error.php');
      exit;
   }
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/Exception.php';
require __DIR__ . '/PHPMailer.php';
require __DIR__ . '/SMTP.php';

function ValidateEmail($email)
{
   $pattern = '/^([0-9a-z]([-.\w]*[0-9a-z])*@(([0-9a-z])+([-\w]*[0-9a-z])*\.)+[a-z]{2,6})$/i';
   return preg_match($pattern, $email);
}
function ReplaceVariables($code)
{
   foreach ($_POST as $key => $value) {
      if (is_array($value)) {
         $value = implode(",", $value);
      }
      $name = "$" . $key;
      $code = str_replace($name, $value, $code);
   }
   $code = str_replace('$ipaddress', $_SERVER['REMOTE_ADDR'], $code);
   return $code;
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['formid']) && $_POST['formid'] == 'contact_form') {
   $mailto = 'vendas@graficadesign.net.br';
   $mailfrom = isset($_POST['email']) ? $_POST['email'] : $mailto;
   ini_set('sendmail_from', $mailfrom);
   $subject = 'Formulário de Contato';
   $message = '<font style="color:#000000;font-family:Arial;font-size:16px">Formulário de Contato:<br><br></font><font style="color:#000000;font-family:Open Sans;font-size:16px"><strong>Nome: </strong>$nome<br><strong>E-mail: </strong>$email<br><strong>Telefone: </strong>$telefone<br><br><strong>Mensagem:<br></strong>$mensagem<br><br></font><font style="color:#000000;font-family:Open Sans;font-size:11px"><i>Enviado por:</i> $ipaddress</font><font style="color:#000000;font-family:Open Sans;font-size:16px"><br></font>';
   $success_url = './form_success';
   $error_url = './form_error';
   $autoresponder_from = 'noreply@graficadesign.net.br';
   $autoresponder_name = 'Gráfica Design';
   $autoresponder_to = isset($_POST['email']) ? $_POST['email'] : $mailfrom;
   $autoresponder_subject = 'Boas Notícias!!!';
   $autoresponder_message = '<font style="color:#000000;font-family:Open Sans;font-size:16px">Olá! $nome<br><br>Agradecemos por entrar em contato conosco.<br>Gostariamos de informá-lo que recebemos seu pedido e que nossa equipe retornará esse contato em até 24 horas.<br><br>Atenciosamente,<br><br>Equipe GráficaDesign.NET</font>';
   $eol = "\n";
   $error = '';

   $mail = new PHPMailer(true);
   try {
      $mail->IsSendmail();
      $subject = ReplaceVariables($subject);
      $mail->Subject = stripslashes($subject);
      $mail->From = $mailfrom;
      $mail->FromName = $mailfrom;
      $mailto_array = explode(",", $mailto);
      for ($i = 0; $i < count($mailto_array); $i++) {
         if (trim($mailto_array[$i]) != "") {
            $mail->AddAddress($mailto_array[$i], "");
         }
      }
      if (!ValidateEmail($mailfrom)) {
         $error .= "The specified email address (" . $mailfrom . ") is invalid!\n<br>";
         throw new Exception($error);
      }
      $mail->AddReplyTo($mailfrom);
      foreach ($_POST as $key => $value) {
         if (preg_match('/www\.|http:|https:/i', $value)) {
            $error .= "URLs are not allowed!\n<br>";
            throw new Exception($error);
            break;
         }
      }
      $mail->CharSet = 'UTF-8';
      if (!empty($_FILES)) {
         foreach ($_FILES as $key => $value) {
            if (is_array($_FILES[$key]['name'])) {
               $count = count($_FILES[$key]['name']);
               for ($file = 0; $file < $count; $file++) {
                  if ($_FILES[$key]['error'][$file] == 0) {
                     $mail->AddAttachment($_FILES[$key]['tmp_name'][$file], $_FILES[$key]['name'][$file]);
                  }
               }
            } else {
               if ($_FILES[$key]['error'] == 0) {
                  $mail->AddAttachment($_FILES[$key]['tmp_name'], $_FILES[$key]['name']);
               }
            }
         }
      }
      $message = ReplaceVariables($message);
      $message = stripslashes($message);
      $mail->MsgHTML($message);
      $mail->IsHTML(true);
      $mail->Send();
      if (!ValidateEmail($autoresponder_from)) {
         $error .= "The specified autoresponder email address (" . $autoresponder_from . ") is invalid!\n<br>";
         throw new Exception($error);
      }

      $mail->ClearAddresses();
      $mail->ClearAttachments();
      $mail->ClearReplyTos();
      $autoresponder_subject = ReplaceVariables($autoresponder_subject);
      $mail->Subject = stripslashes($autoresponder_subject);
      $mail->From = $autoresponder_from;
      $mail->FromName = $autoresponder_name;
      $mail->AddAddress($autoresponder_to, "");
      $mail->AddReplyTo($autoresponder_from);
      $autoresponder_message = ReplaceVariables($autoresponder_message);
      $autoresponder_message = stripslashes($autoresponder_message);
      $mail->MsgHTML($autoresponder_message);
      $mail->IsHTML(true);
      $mail->Send();
      header('Location: ' . $success_url);
   } catch (Exception $e) {
      $errorcode = file_get_contents($error_url);
      $replace = "##error##";
      $errorcode = str_replace($replace, $e->getMessage(), $errorcode);
      echo $errorcode;
   }
   exit;
}
?>
<!doctype html>
<html lang="pt-br">

<head>
   <meta charset="utf-8">
   <title>Gráfica Design</title>
   <meta name="description" content="O Site da Gráfica Design foi desenvolvido para ajudar você na escolha do melhor produto para sua empresa. Entre em contato e comprove nossa qualidade.">
   <meta name="keywords" content="Lacres Personalizados para Tag">
   <meta name="author" content="Agência Citrino">
   <meta name="robots" content="index, follow">
   <meta name="revisit-after" content="31 days">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <link href="ico16.ico" rel="shortcut icon" type="image/x-icon">
   <link href="ico32.png" rel="icon" sizes="34x34" type="image/png">
   <link href="ico64.png" rel="icon" sizes="69x68" type="image/png">
   <link href="font-awesome.min.css" rel="stylesheet">
   <link href="https://fonts.googleapis.com/css?family=Noticia+Text&display=swap" rel="stylesheet">
   <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400&display=swap" rel="stylesheet">
   <link href="graficadesign.css?v=104" rel="stylesheet">
   <link href="index.css?v=104" rel="stylesheet">
   <script src="jquery-3.6.0.slim.min.js"></script>
   <script src="skrollr.min.js"></script>
   <script src="jquery.inputmask.min.js"></script>
   <script src="jquery-ui.min.js"></script>
   <script src="util.min.js"></script>
   <script src="modal.min.js"></script>
   <script>
      function submitcontato() {
         var regexp;
         var nome_contato_form = document.getElementById('nome_contato_form');
         if (!(nome_contato_form.disabled || nome_contato_form.style.display === 'none' || nome_contato_form.style
               .visibility === 'hidden')) {
            if (nome_contato_form.value == "") {
               alert("Por Favor, digite seu nome.");
               nome_contato_form.focus();
               return false;
            }
            if (nome_contato_form.value.length < 3) {
               alert("Por Favor, digite seu nome.");
               nome_contato_form.focus();
               return false;
            }
            if (nome_contato_form.value.length > 56) {
               alert("Por Favor, digite seu nome.");
               nome_contato_form.focus();
               return false;
            }
         }
         var email_contato_form = document.getElementById('email_contato_form');
         if (!(email_contato_form.disabled || email_contato_form.style.display === 'none' || email_contato_form.style
               .visibility === 'hidden')) {
            regexp = /^([0-9a-z]([-.\w]*[0-9a-z])*@(([0-9a-z])+([-\w]*[0-9a-z])*\.)+[a-z]{2,6})$/i;
            if (!regexp.test(email_contato_form.value)) {
               alert("Por Favor, digite seu e-mail.");
               email_contato_form.focus();
               return false;
            }
            if (email_contato_form.value == "") {
               alert("Por Favor, digite seu e-mail.");
               email_contato_form.focus();
               return false;
            }
            if (email_contato_form.value.length < 8) {
               alert("Por Favor, digite seu e-mail.");
               email_contato_form.focus();
               return false;
            }
            if (email_contato_form.value.length > 56) {
               alert("Por Favor, digite seu e-mail.");
               email_contato_form.focus();
               return false;
            }
         }
         var telefone_contato_form = document.getElementById('telefone_contato_form');
         if (!(telefone_contato_form.disabled || telefone_contato_form.style.display === 'none' || telefone_contato_form
               .style.visibility === 'hidden')) {
            if (telefone_contato_form.value == "") {
               alert("Por Favor, digite seu telefone.");
               telefone_contato_form.focus();
               return false;
            }
         }
         $("#_alertform").modal('show');
         return true;
      }
   </script>
   <script src="https://hcaptcha.com/1/api.js" async defer></script>
   <script>
      $(document).ready(function() {
         function skrollrInit() {
            skrollr.init({
               forceHeight: false,
               mobileCheck: function() {
                  return false;
               },
               smoothScrolling: false
            });
         }
         skrollrInit();
         $("#telefone_contato_form").inputmask('(99) 99999-9999');
         $("#_alertform").on('hidden.bs.modal', function(e) {
            document.getElementById('contact_form').reset();
         });
         $('#_alertform .modal-dialog').draggable({
            handle: '.modal-header'
         });
      });
   </script>
   <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet">
   <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
   <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
   <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">
   <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i,800,800i&amp;subset=cyrillic,cyrillic-ext,greek,greek-ext,latin-ext,vietnamese" rel="stylesheet">

   <!-- Global site tag (gtag.js) - Google Ads: 783113379 -->
   <script async src="https://www.googletagmanager.com/gtag/js?id=AW-783113379"></script>
   <script>
      window.dataLayer = window.dataLayer || [];

      function gtag() {
         dataLayer.push(arguments);
      }
      gtag('js', new Date());

      gtag('config', 'AW-783113379');
   </script>

   <!-- Google Tag Manager -->
   <script>
      (function(w, d, s, l, i) {
         w[l] = w[l] || [];
         w[l].push({
            'gtm.start': new Date().getTime(),
            event: 'gtm.js'
         });
         var f = d.getElementsByTagName(s)[0],
            j = d.createElement(s),
            dl = l != 'dataLayer' ? '&l=' + l : '';
         j.async = true;
         j.src =
            'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
         f.parentNode.insertBefore(j, f);
      })(window, document, 'script', 'dataLayer', 'GTM-NV76LVC');
   </script>
   <!-- End Google Tag Manager -->
</head>

<body>
   <div id="_alertform" class="modal" role="dialog">
      <div class="modal-dialog modal-dialog-centered">
         <div class="modal-content">
            <div class="modal-header">
               <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
               <h4 class="modal-title">Resultado</h4>
            </div>
            <div class="modal-body">
               <iframe allowtransparency="true" frameborder="0" id="_alertform-iframe" name="_alertform-iframe" src="about:blank"></iframe>
            </div>
         </div>
      </div>
   </div>
   <div id="menu-retorn-topo" data-250-start="opacity:1;" data-start="opacity:0;">
      <div id="wb_icon-retorn-topo">
         <a href="#topo" rel="nofollow" title="Voltar ao Topo">
            <div id="icon-retorn-topo"><i class="fa fa-angle-up"></i></div>
         </a>
      </div>
   </div>
   <nav id="socialmedia_share" title="Social Media Sticky">
      <a href="https://contate.me/graficadesign" rel="nofollow" target="_blank" title="WhatsApp"><img src="images/img0001.webp" id="share-whatsapp" alt="Entre em contato pelo WhatsApp" title="WhatsApp" width="150" height="44"></a>
      <a href="https://www.instagram.com/graficadesign.oficial" rel="nofollow" target="_blank" title="Instagram"><img src="images/img0002.webp" id="MergedObject1" alt="Visite nossa página no Instagram." title="Instagram" width="150" height="44"></a>
      <a href="mailto:vendas@graficadesign.net.br" rel="nofollow" title="E-mail"><img src="images/img0003.webp" id="MergedObject2" alt="Entre em contato por e-mail." title="E-mail" width="150" height="44"></a>
   </nav>
   <div id="wb_topo">
      <div id="topo">
         <div class="row">
            <div class="col-1">
               <div id="wb_logo-topo">
                  <a href="https://www.graficadesign.net.br" title="Bem vindo a GráficaDesign.NET"><img src="images/logo.png" id="logo-topo" alt="Logo" width="396" height="78"></a>
               </div>
            </div>
            <div class="col-2">
            </div>
            <div class="col-3">
               <div id="wb_ico-instagram-top">
                  <a href="https://www.instagram.com/graficadesign.oficial" rel="nofollow" target="_blank" title="Instagram ">
                     <div id="ico-instagram-top"><i class="fa fa-instagram"></i></div>
                  </a>
               </div>
               <div id="wb_ico-whatsapp-top">
                  <a href="https://contate.me/graficadesign" rel="nofollow" target="_blank" title="Entre em contato por WhatsApp.">
                     <div id="ico-whatsapp-top"><i class="fa fa-whatsapp"></i></div>
                  </a>
               </div>
            </div>
         </div>
      </div>
   </div>
   <div id="wb_barra-topo">
      <div id="barra-topo">
         <div class="row">
            <div class="col-1">
            </div>
         </div>
      </div>
   </div>
   <div id="wb_grade-principal">
      <div id="grade-principal">
         <div class="col-1">
            <div class="col-1-padding">
               <div id="wb_Heading1">
                  <h1 id="Heading1-custom">Já pensou em proteger o seu produto contra falsificação?</h1>
               </div>
               <div id="wb_Heading2">
                  <h2 id="Heading2">E se o seu produto fosse confundido com uma mercadoria pirata?</h2>
               </div>
               <div id="WrapText1">
                  <div id="WrapText1-float">
                  </div>
                  <!-- <div class="WrapText1">O Lacre Personalizados para Tag é desenvolvido para o setor de confecções
                            e tem como principal finalidade identificar o seu produto. O Lacres Personalizados para Tag
                            é de grande importância, ajudando a destacar sua marca.<br><br>O Lacre para Tag vem
                            personalizado com sua logo ou marca tornando suas peças ainda mais autenticas <br><br>São
                            produzidas em diversas cores, sua cordoalha de nylon possui 25cm de comprimento e duas
                            travas, onde uma prende a tag e a outra a peça desejada. <br>O Lacre para Tag é gravado com
                            técnologia de alta definição garantindo uma maior visibilidade a sua marca. </div> -->
                  <div class="WrapText1">
                     <p>
                        O <strong>Lacre Personalizado para Tag</strong> é uma solução inovadora e segura
                        desenvolvida especialmente para o setor de confecções. Sua principal função é
                        identificar e proteger seu produto, destacando sua marca no mercado competitivo. Com
                        nossa tecnologia de alta definição, cada lacre vem personalizado com sua logo ou marca,
                        garantindo visibilidade e autenticidade às suas peças.
                     </p>
                     <p>
                        Comercializamos <strong>Lacres Personalizados para Tags</strong> de roupas, uma escolha
                        ideal para consolidar sua marca e agregar valor ao seu produto. Muitas lojas já adotaram
                        esse acessório por sua capacidade de diferenciar peças genuínas de imitações, aumentando
                        a segurança e a confiança do cliente.
                     </p>
                     <p>
                        Além de ser um item de ótima qualidade, nosso lacre personalizado é fabricado sob
                        encomenda, permitindo a escolha de arte, cores e formatos específicos. Isso garante que
                        cada lacre seja único e adequado às necessidades de sua marca. Produzimos até 3000 peças
                        personalizadas por cliente, com a conveniência de frete gratuito para todo o Brasil.
                        Essa é a nossa forma de facilitar o acesso à qualidade e exclusividade que seu produto
                        merece.
                     </p>
                     <p>
                        Os lacres são produzidos em diversas cores e a cordoalha de nylon possui 25cm de
                        comprimento com duas travas, assegurando a fixação tanto na tag quanto na peça desejada.
                        Escolha o <strong>Lacre Personalizado para Tag</strong> e dê ao seu produto a identidade
                        visual que ele merece.
                     </p>
                  </div>

               </div>
            </div>
         </div>
         <div class="col-2">
            <div id="wb_imagens-principal" class="card">
               <div id="imagens-principal-card-body">
                  <a href="https://www.instagram.com/graficadesign.oficial" target="_blank" title="Veja mais fotos em nosso Instagram." rel="nofollow"><img id="imagens-principal-card-item0" src="images/l102.webp" width="1020" height="898" alt="Lacre Personalizado para Tag L10" title="Veja mais fotos em nosso Instagram."></a>
                  <a href="https://www.instagram.com/graficadesign.oficial" target="_blank" title="Veja mais fotos em nosso Instagram." rel="nofollow"><img id="imagens-principal-card-item1" src="images/l103.webp" width="707" height="594" alt="Lacre Personalizado para Tag L10" title="Veja mais fotos em nosso Instagram."></a>
                  <a href="https://www.instagram.com/graficadesign.oficial" target="_blank" title="Veja mais fotos em nosso Instagram." rel="nofollow"><img id="imagens-principal-card-item2" src="images/l104.webp" width="813" height="681" alt="Lacre Personalizado para Tag L10" title="Veja mais fotos em nosso Instagram."></a>
               </div>
            </div>
         </div>
         <div class="col-3">
            <div class="col-3-padding">
               <div id="wb_contact_form">
                  <form name="contato" method="post" action="<?php echo basename(__FILE__); ?>" enctype="multipart/form-data" accept-charset="UTF-8" target="_alertform-iframe" id="contact_form" onsubmit="return submitcontato()">
                     <input type="hidden" name="formid" value="contact_form">
                     <input type="hidden" name="" value="">
                     <div class="row">
                        <div class="col-1">
                           <div id="wb_chamada-formulario">
                              <span id="wb_uid0"><strong>Solicite um Orçamento.</strong></span>
                           </div>
                           <label for="nome_contato_texto" id="nome_contato_texto" title="Nome: ">Nome*:</label>
                           <input type="text" id="nome_contato_form" name="nome" value="" maxlength="56" autocomplete="off" spellcheck="false" title="Qual seu nome?" placeholder="Qual seu nome?">
                           <label for="email_contato_texto" id="email_contato_texto" title="E-mail: ">E-mail*:</label>
                           <input type="email" id="email_contato_form" name="email" value="" maxlength="56" autocomplete="off" spellcheck="false" title="Qual seu e-mail?" placeholder="Qual seu e-mail?">
                           <label for="telefone_contato_texto" id="telefone_contato_texto" title="Telefone: ">Telefone*:</label>
                           <input type="tel" id="telefone_contato_form" name="telefone" value="" autocomplete="off" spellcheck="false" title="Qual número do seu telefone?" placeholder="Qual seu telefone com DDD?">
                           <label for="botao-enviar" id="mensagem_contato_texto" title="Mensagem: ">Mensagem:</label>
                           <textarea name="mensagem" id="mensagem_contato_form" rows="4" cols="41" maxlength="188" autocomplete="off" spellcheck="false" title="Digite aqui sua mensagem" placeholder="Digite aqui sua mensagem"></textarea>
                           <button type="submit" id="botao-enviar" name="" value="Vamos lá!!!" class="ui-button ui-corner-all ui-widget">Vamos lá!!!</button>
                           <div id="wb_Extension1">
                              <div class="h-captcha" data-sitekey="faeeb951-c4c7-436a-bb32-e5e987336f8a">
                              </div>

                           </div>
                        </div>
                     </div>
                  </form>
               </div>
            </div>
         </div>
      </div>
   </div>
   <div id="wb_rodape">
      <div id="rodape">
         <div class="col-1">
            <div id="wb_logo-rodape">
               <a href="https://www.graficadesign.net.br"><img src="images/logo%2dfooter.png" id="logo-rodape" alt="Logo Rodapé" title="GráficaDesign.NET" width="311" height="60"></a>
            </div>
         </div>
         <div class="col-2">
            <div id="wb_copyright">
               <span id="wb_uid1">Copyright © 2022 - Todos os direitos reservados<br>Made with &#10084;</span>
            </div>
         </div>
         <div class="col-3">
         </div>
      </div>
   </div>
   <!-- Google Tag Manager (noscript) -->
   <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-NV76LVC" height="0" width="0"></iframe></noscript>
   <!-- End Google Tag Manager (noscript) -->
</body>

</html>