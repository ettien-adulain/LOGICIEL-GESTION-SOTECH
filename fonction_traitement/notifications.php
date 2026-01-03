<?php
include '../db/connecting.php';
include '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Twilio\Rest\Client;

class Notifications {
    private $config_email;
    private $config_sms;

    public function __construct() {
        // Récupérer les configurations
        $this->config_email = selection_element('configuration_email', ['desactiver' => 'non'])[0];
        $this->config_sms = selection_element('configuration_sms', ['desactiver' => 'non'])[0];
    }

    // Récupérer les détails de la vente
    private function getDetailsVente($numero_vente) {
        $details = [];
        
        // Récupérer les informations de la vente
        $vente = selection_element('vente', ['numero_vente' => $numero_vente])[0];
        
        // Récupérer les articles de la vente
        $articles = selection_element('vente_article', ['numero_vente' => $numero_vente]);
        
        // Récupérer les informations du client
        $client = selection_element('client', ['id' => $vente['client_id']])[0];
        
        // Récupérer les paiements
        $paiements = selection_element('paiement', ['numero_vente' => $numero_vente]);
        
        $details['client'] = $client;
        $details['vente'] = $vente;
        $details['articles'] = [];
        $details['paiements'] = [];
        
        foreach ($articles as $article) {
            $article_info = selection_element('article', ['id' => $article['article_id']])[0];
            $details['articles'][] = [
                'nom' => $article_info['libelle'],
                'quantite' => $article['quantite'],
                'prix' => $article['prix_vente'],
                'numero_serie' => $article_info['numero_serie'] ?? 'N/A'
            ];
        }

        foreach ($paiements as $paiement) {
            $mode_paiement = selection_element('mode_reglement', ['id' => $paiement['mode_paiement_id']])[0];
            $details['paiements'][] = [
                'mode' => $mode_paiement['libelle'],
                'montant' => $paiement['montant'],
                'date' => $paiement['date_paiement']
            ];
        }
        
        return $details;
    }

    // Envoyer un email
    public function envoyerEmail($numero_vente, $montant_total) {
        try {
            $details = $this->getDetailsVente($numero_vente);
            $email = $details['client']['email'];
            
            if (empty($email)) {
                return false;
            }

            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $this->config_email['smtp_server'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config_email['email_address'];
            $mail->Password = $this->config_email['email_password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->config_email['smtp_port'];
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($this->config_email['email_address'], 'SOTech');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Confirmation de votre achat - Facture #' . $numero_vente;

            // Préparer le tableau des articles
            $articles_html = '';
            foreach ($details['articles'] as $article) {
                $articles_html .= "
                    <tr>
                        <td>{$article['nom']}</td>
                        <td>{$article['quantite']}</td>
                        <td>{$article['prix']} F.CFA</td>
                        <td>{$article['numero_serie']}</td>
                    </tr>
                ";
            }

            // Préparer le tableau des paiements
            $paiements_html = '';
            foreach ($details['paiements'] as $paiement) {
                $paiements_html .= "
                    <tr>
                        <td>{$paiement['mode']}</td>
                        <td>{$paiement['montant']} F.CFA</td>
                        <td>{$paiement['date']}</td>
                    </tr>
                ";
            }

            $message = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        .container { padding: 20px; }
                        .header { background-color: #f8f9fa; padding: 20px; text-align: center; }
                        .content { padding: 20px; }
                        .footer { text-align: center; padding: 20px; font-size: 12px; }
                        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
                        th { background-color: #f8f9fa; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>Merci pour votre confiance, {$details['client']['nom']} !</h2>
                        </div>
                        <div class='content'>
                            <p>Cher(e) client(e),</p>
                            <p>Nous vous remercions pour votre achat chez SOTech. Voici le récapitulatif de votre commande :</p>
                            
                            <h3>Détails de la commande</h3>
                            <table>
                                <tr>
                                    <th>Article</th>
                                    <th>Quantité</th>
                                    <th>Prix</th>
                                    <th>Numéro de série</th>
                                </tr>
                                {$articles_html}
                            </table>

                            <h3>Détails des paiements</h3>
                            <table>
                                <tr>
                                    <th>Mode de paiement</th>
                                    <th>Montant</th>
                                    <th>Date</th>
                                </tr>
                                {$paiements_html}
                            </table>
                            
                            <p><strong>Numéro de facture :</strong> {$numero_vente}</p>
                            <p><strong>Montant total :</strong> {$montant_total} F.CFA</p>
                            <p><strong>Date d'achat :</strong> " . date('d/m/Y H:i') . "</p>
                            
                            <p>Nous vous remercions de votre confiance et espérons vous revoir bientôt !</p>
                        </div>
                        <div class='footer'>
                            <p>Pour toute question, n'hésitez pas à nous contacter.</p>
                            <p>Cordialement,<br>L'équipe SOTech</p>
                        </div>
                    </div>
                </body>
                </html>
            ";

            $mail->Body = $message;
            $mail->AltBody = "Merci pour votre achat chez SOTech !\n\n" .
                            "Numéro de facture : {$numero_vente}\n" .
                            "Montant total : {$montant_total} F.CFA\n" .
                            "Date d'achat : " . date('d/m/Y H:i') . "\n\n" .
                            "Cordialement,\nL'équipe SOTech";

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Erreur d'envoi d'email : " . $e->getMessage());
            return false;
        }
    }

    // Envoyer un SMS
    public function envoyerSMS($numero_vente, $montant_total) {
        try {
            $details = $this->getDetailsVente($numero_vente);
            $numero = $details['client']['numero'];
            
            if (empty($numero)) {
                return false;
            }

            $message = "Cher(e) {$details['client']['nom']},\n\n" .
                      "Merci pour votre achat chez SOTech !\n\n" .
                      "Facture #{$numero_vente}\n" .
                      "Montant total : {$montant_total} F.CFA\n" .
                      "Date : " . date('d/m/Y H:i') . "\n\n" .
                      "Détails des articles :\n";

            foreach ($details['articles'] as $article) {
                $message .= "- {$article['nom']} (Série: {$article['numero_serie']})\n";
            }

            $message .= "\nDétails des paiements :\n";
            foreach ($details['paiements'] as $paiement) {
                $message .= "- {$paiement['mode']} : {$paiement['montant']} F.CFA\n";
            }

            $message .= "\nMerci de votre confiance !\nSOTech";

            $client = new Client(
                $this->config_sms['account_id'],
                $this->config_sms['auth_token']
            );

            $client->messages->create(
                $numero,
                [
                    'from' => $this->config_sms['sender_number'],
                    'body' => $message
                ]
            );

            return true;
        } catch (Exception $e) {
            error_log("Erreur d'envoi de SMS : " . $e->getMessage());
            return false;
        }
    }

    // Envoyer toutes les notifications
    public function envoyerNotifications($numero_vente, $montant_total) {
        return [
            'email_sent' => $this->envoyerEmail($numero_vente, $montant_total),
            'sms_sent' => $this->envoyerSMS($numero_vente, $montant_total)
        ];
    }
} 