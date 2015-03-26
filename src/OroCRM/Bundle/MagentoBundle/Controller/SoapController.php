<?php

namespace OroCRM\Bundle\MagentoBundle\Controller;

use OroCRM\Bundle\MagentoBundle\Provider\ChannelType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use Oro\Bundle\IntegrationBundle\Provider\ConnectorInterface;
use Oro\Bundle\IntegrationBundle\Provider\TransportInterface;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;

use OroCRM\Bundle\MagentoBundle\Entity\MagentoSoapTransport;
use OroCRM\Bundle\MagentoBundle\Provider\ExtensionAwareInterface;
use OroCRM\Bundle\MagentoBundle\Provider\Iterator\StoresSoapIterator;
use OroCRM\Bundle\MagentoBundle\Provider\Transport\SoapTransport;

class SoapController extends Controller
{
    /**
     * @Route("/check", name="orocrm_magento_soap_check")
     * @AclAncestor("oro_integration_update")
     */
    public function checkAction(Request $request)
    {
        $transport = $this->get('orocrm_magento.transport.soap_transport');
        $transport->setMultipleAttemptsEnabled(false);
        $transportEntity = $this->getTransportEntity($request, $transport);

        $response = ['success' => false];
        try {
            $transport->init($transportEntity);

            $extensionVersion = $transport->getExtensionVersion();
            $isExtensionInstalled = !empty($extensionVersion);
            $isSupportedVersion = $transport->isSupportedExtensionVersion();
            $allowedTypesChoices = $this->getAllowedConnectorsChoices($isExtensionInstalled, $isSupportedVersion);
            $response = [
                'success' => true,
                'websites' => $this->formatWebsiteChoices($transport->getWebsites()),
                'isExtensionInstalled' => $isExtensionInstalled,
                'magentoVersion' => $transport->getMagentoVersion(),
                'extensionVersion' => $extensionVersion,
                'requiredExtensionVersion' => SoapTransport::REQUIRED_EXTENSION_VERSION,
                'isSupportedVersion' => $isSupportedVersion,
                'connectors' => $allowedTypesChoices,
                'adminUrl' => $transport->getAdminUrl(),
            ];
        } catch (\Exception $e) {
            $this->get('logger')->critical(sprintf('MageCheck error: %s: %s', $e->getCode(), $e->getMessage()));
        }

        return new JsonResponse($response);
    }

    /**
     * Example:
     * [
     *      WebsiteId => 'Website: WebsiteId, Stores: Store1, Store2'
     * ]
     *
     * @param \Iterator $websitesSource
     *
     * @return array
     */
    protected function formatWebsiteChoices(\Iterator $websitesSource)
    {
        $translator = $this->get('translator');
        $websites = iterator_to_array($websitesSource);
        $websites = array_map(
            function ($website) use ($translator) {
                return [
                    'id' => $website['id'],
                    'label' => $translator->trans(
                        'Website ID: %websiteId%, Stores: %storesList%',
                        [
                            '%websiteId%' => $website['id'],
                            '%storesList%' => $website['name']
                        ]
                    )
                ];
            },
            $websites
        );

        // Delete Admin website
        foreach ($websites as $key => $website) {
            if ($website['id'] == StoresSoapIterator::ADMIN_WEBSITE_ID) {
                unset($websites[$key]);
            }
        }

        // Add all web sites choice
        array_unshift(
            $websites,
            [
                'id' => StoresSoapIterator::ALL_WEBSITES,
                'label' => $translator->trans('orocrm.magento.magentosoaptransport.all_sites')
            ]
        );

        return $websites;
    }

    /**
     * @param bool $isExtensionInstalled
     * @param bool $isSupportedVersion
     * @return array
     */
    protected function getAllowedConnectorsChoices($isExtensionInstalled, $isSupportedVersion)
    {
        $allowedTypesChoices = $this->get('oro_integration.manager.types_registry')
            ->getAvailableConnectorsTypesChoiceList(
                ChannelType::TYPE,
                function (ConnectorInterface $connector) use ($isExtensionInstalled, $isSupportedVersion) {
                    if ($connector instanceof ExtensionAwareInterface) {
                        return $isExtensionInstalled && $isSupportedVersion;
                    }

                    return true;
                }
            );

        $translator = $this->get('translator');
        foreach ($allowedTypesChoices as $name => $val) {
            $allowedTypesChoices[$name] = $translator->trans($val);
        }

        return $allowedTypesChoices;
    }

    /**
     * Transport setting entity should be set to form
     * in case when password should be merged from saved data
     *
     * @param Request $request
     * @param TransportInterface $transport
     * @return MagentoSoapTransport
     */
    protected function getTransportEntity(Request $request, $transport)
    {
        $data = null;
        $id = $request->get('id', false);
        if ($id) {
            $data = $this->get('doctrine.orm.entity_manager')->find($transport->getSettingsEntityFQCN(), $id);
        }

        $form = $this->get('form.factory')
            ->createNamed('soap-check', $transport->getSettingsFormType(), $data, ['csrf_protection' => false]);
        $form->submit($request);

        return $form->getData();
    }
}
