<?php
// bin/magento bredabeds:email-attachments:email-confirmation 'dustin@ootri.com' 'https://bredabeds.com/murphy-beds'
namespace BredaBeds\EmailAttachments\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SendOrderConfirmation extends Command
{
public function __construct(
    protected \Magento\Framework\App\State $state,
    protected \BredaBeds\EmailAttachments\Helper\SendBasicEmail $sendBasicEmail,
    protected \BredaBeds\EmailAttachments\Helper\Browserless $browserless
) { parent::__construct(); }

protected function configure()
{
    $this->setName('bredabeds:email-attachments:email-confirmation')
        ->setDescription('Test sending order confirmation email with an attachment')
        ->addArgument('to', InputArgument::REQUIRED, 'To address')
        ->addArgument('attachment', InputArgument::REQUIRED, 'Path to attachment');
    parent::configure();
}

protected function execute(InputInterface $input, OutputInterface $output): int
{
    try { // Set the area code if it is not already set.
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_FRONTEND);
    } catch (\Exception $e) { }

    // Check if path supplied is a file (on the file system), if it doesn't then assume it's a URL and generate a new one
    if (!file_exists($absolutePath = $input->getArgument('attachment'))) 
        $absolutePath = $this->browserless->generatePdfFromUrl($absolutePath, 'test.pdf', 'pdf/files');

    try {
        $this->sendBasicEmail->sendEmail(
            $input->getArgument('to'),
            'Your PDF Document',
            'Your PDF document is attached. Please contact us if you have any questions or concerns.',
            [$absolutePath]
        );
    } catch (\Exception $e) {
        // Handle or log error
        throw $e;
    }

    return Command::SUCCESS;
}
}
