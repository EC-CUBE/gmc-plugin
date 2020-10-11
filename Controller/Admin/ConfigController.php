<?php

namespace Plugin\GMC\Controller\Admin;

use Eccube\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ConfigController extends AbstractController
{
    /**
     * @Route("/%eccube_admin_route%/gmc/config", name="gmc_admin_config")
     * @Template("@GMC/admin/config.twig")
     */
    public function index(Request $request)
    {
        $form = $this->createForm(FormType::class)
            ->add('verification_file', FileType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $data = $form->getData();
            /* @var UploadedFile $file */
            $file = $data['verification_file'];
            $file->move($this->eccubeConfig['plugin_data_realdir'] . '/GMC', $file->getClientOriginalName());

            $this->addSuccess('登録しました。', 'admin');
            return $this->redirectToRoute('gmc_admin_config');
        }

        return [
            'form' => $form->createView(),
            'gmc_proxy_url' => env('GMC_PROXY_URL', $this->eccubeConfig['gmc_proxy_url'])
        ];
    }
}
