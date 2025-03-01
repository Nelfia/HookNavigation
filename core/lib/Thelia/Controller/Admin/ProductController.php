<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Controller\Admin;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\FeatureProduct\FeatureProductDeleteEvent;
use Thelia\Core\Event\FeatureProduct\FeatureProductUpdateEvent;
use Thelia\Core\Event\MetaData\MetaDataCreateOrUpdateEvent;
use Thelia\Core\Event\MetaData\MetaDataDeleteEvent;
use Thelia\Core\Event\Product\ProductAddAccessoryEvent;
use Thelia\Core\Event\Product\ProductAddCategoryEvent;
use Thelia\Core\Event\Product\ProductAddContentEvent;
use Thelia\Core\Event\Product\ProductCloneEvent;
use Thelia\Core\Event\Product\ProductCombinationGenerationEvent;
use Thelia\Core\Event\Product\ProductCreateEvent;
use Thelia\Core\Event\Product\ProductDeleteAccessoryEvent;
use Thelia\Core\Event\Product\ProductDeleteCategoryEvent;
use Thelia\Core\Event\Product\ProductDeleteContentEvent;
use Thelia\Core\Event\Product\ProductDeleteEvent;
use Thelia\Core\Event\Product\ProductEvent;
use Thelia\Core\Event\Product\ProductSetTemplateEvent;
use Thelia\Core\Event\Product\ProductToggleVisibilityEvent;
use Thelia\Core\Event\Product\ProductUpdateEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementCreateEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementDeleteEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\Loop\Document;
use Thelia\Core\Template\Loop\Image;
use Thelia\Core\Template\ParserContext;
use Thelia\Form\BaseForm;
use Thelia\Form\Definition\AdminForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Form\ProductModificationForm;
use Thelia\Model\AccessoryQuery;
use Thelia\Model\AttributeAv;
use Thelia\Model\AttributeAvQuery;
use Thelia\Model\AttributeQuery;
use Thelia\Model\CategoryI18nQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\Content;
use Thelia\Model\ContentQuery;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Feature;
use Thelia\Model\FeatureProductQuery;
use Thelia\Model\FeatureQuery;
use Thelia\Model\FeatureTemplateQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\MetaData;
use Thelia\Model\MetaDataQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductAssociatedContentQuery;
use Thelia\Model\ProductDocument;
use Thelia\Model\ProductDocumentQuery;
use Thelia\Model\ProductI18nQuery;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\ProductPrice;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElements as ProductSaleElementsModel;
use Thelia\Model\ProductSaleElementsProductDocument;
use Thelia\Model\ProductSaleElementsProductDocumentQuery;
use Thelia\Model\ProductSaleElementsProductImage;
use Thelia\Model\ProductSaleElementsProductImageQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\TaxRuleQuery;
use Thelia\TaxEngine\Calculator;
use Thelia\Type\BooleanOrBothType;

/**
 * Manages products.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class ProductController extends AbstractSeoCrudController
{
    public function __construct()
    {
        parent::__construct(
            'product',
            'manual',
            'product_order',
            AdminResources::PRODUCT,
            TheliaEvents::PRODUCT_CREATE,
            TheliaEvents::PRODUCT_UPDATE,
            TheliaEvents::PRODUCT_DELETE,
            TheliaEvents::PRODUCT_TOGGLE_VISIBILITY,
            TheliaEvents::PRODUCT_UPDATE_POSITION,
            TheliaEvents::PRODUCT_UPDATE_SEO
        );
    }

    /**
     * Attributes ajax tab loading.
     */
    public function loadAttributesAjaxTabAction()
    {
        return $this->render(
            'ajax/product-attributes-tab',
            [
                'product_id' => $this->getRequest()->get('product_id', 0),
            ]
        );
    }

    /**
     * Related information ajax tab loading.
     */
    public function loadRelatedAjaxTabAction()
    {
        return $this->render(
            'ajax/product-related-tab',
            [
                    'product_id' => $this->getRequest()->get('product_id', 0),
                    'folder_id' => $this->getRequest()->get('folder_id', 0),
                    'accessory_category_id' => $this->getRequest()->get('accessory_category_id', 0),
            ]
        );
    }

    protected function getCreationForm()
    {
        return $this->createForm(AdminForm::PRODUCT_CREATION);
    }

    protected function getUpdateForm()
    {
        return $this->createForm(AdminForm::PRODUCT_MODIFICATION, FormType::class, [], []);
    }

    protected function getCreationEvent($formData)
    {
        $createEvent = new ProductCreateEvent();

        $createEvent
            ->setRef($formData['ref'])
            ->setTitle($formData['title'])
            ->setLocale($formData['locale'])
            ->setDefaultCategory($formData['default_category'])
            ->setVisible($formData['visible'])
            ->setVirtual($formData['virtual'])
            ->setBasePrice($formData['price'])
            ->setBaseWeight($formData['weight'])
            ->setCurrencyId($formData['currency'])
            ->setTaxRuleId($formData['tax_rule'])
            ->setBaseQuantity($formData['quantity'])
            ->setTemplateId($formData['template_id'])
        ;

        return $createEvent;
    }

    protected function getUpdateEvent($formData)
    {
        $changeEvent = new ProductUpdateEvent($formData['id']);

        $changeEvent
            ->setLocale($formData['locale'])
            ->setRef($formData['ref'])
            ->setTitle($formData['title'])
            ->setChapo($formData['chapo'])
            ->setDescription($formData['description'])
            ->setPostscriptum($formData['postscriptum'])
            ->setVisible($formData['visible'])
            ->setVirtual($formData['virtual'])
            ->setDefaultCategory($formData['default_category'])
            ->setBrandId($formData['brand_id'])
            ->setVirtualDocumentId($formData['virtual_document_id'])
        ;

        // Create and dispatch the change event
        return $changeEvent;
    }

    protected function createUpdatePositionEvent($positionChangeMode, $positionValue)
    {
        return new UpdatePositionEvent(
            $this->getRequest()->get('product_id', null),
            $positionChangeMode,
            $positionValue,
            $this->getRequest()->get('category_id', null)
        );
    }

    protected function getDeleteEvent()
    {
        return new ProductDeleteEvent($this->getRequest()->get('product_id', 0));
    }

    /**
     * @param ProductEvent $event
     */
    protected function eventContainsObject($event)
    {
        return $event->hasProduct();
    }

    /**
     * @param ProductPrice        $productPrice
     * @param ProductSaleElements $saleElement
     * @param Currency            $defaultCurrency
     * @param Currency            $currentCurrency
     */
    protected function updatePriceFromDefaultCurrency($productPrice, $saleElement, $defaultCurrency, $currentCurrency): void
    {
        // Get price for default currency
        $priceForDefaultCurrency = ProductPriceQuery::create()
        ->filterByCurrency($defaultCurrency)
        ->filterByProductSaleElements($saleElement)
        ->findOne()
        ;

        if ($priceForDefaultCurrency !== null) {
            $productPrice
            ->setPrice($priceForDefaultCurrency->getPrice() * $currentCurrency->getRate())
            ->setPromoPrice($priceForDefaultCurrency->getPromoPrice() * $currentCurrency->getRate())
            ;
        }
    }

    protected function appendValue(&$array, $key, $value): void
    {
        if (!isset($array[$key])) {
            $array[$key] = [];
        }

        $array[$key][] = $value;
    }

    /**
     * @param Product $object
     *
     * @return ProductModificationForm
     */
    protected function hydrateObjectForm(ParserContext $parserContext, $object)
    {
        // Find product's sale elements
        $saleElements = ProductSaleElementsQuery::create()
            ->filterByProduct($object)
            ->find();

        $defaultCurrency = Currency::getDefaultCurrency();
        $currentCurrency = $this->getCurrentEditionCurrency();

        // Common parts
        $defaultPseData = $combinationPseData = [
            'product_id' => $object->getId(),
            'tax_rule' => $object->getTaxRuleId(),
        ];

        /** @var ProductSaleElements $saleElement */
        foreach ($saleElements as $saleElement) {
            // Get the product price for the current currency
            $productPrice = ProductPriceQuery::create()
                ->filterByCurrency($currentCurrency)
                ->filterByProductSaleElements($saleElement)
                ->findOne()
            ;

            // No one exists ?
            if ($productPrice === null) {
                $productPrice = new ProductPrice();

                // If the current currency is not the default one, calculate the price
                // using default currency price and current currency rate
                if ($currentCurrency->getId() != $defaultCurrency->getId()) {
                    $productPrice->setFromDefaultCurrency(true);
                }
            }

            // Caclulate prices if we have to use the rate * default currency price
            if ($productPrice->getFromDefaultCurrency() == true) {
                $this->updatePriceFromDefaultCurrency($productPrice, $saleElement, $defaultCurrency, $currentCurrency);
            }

            $isDefaultPse = \count($saleElement->getAttributeCombinations()) == 0;

            // If this PSE has no combination -> this is the default one
            // affect it to the thelia.admin.product_sale_element.update form
            if ($isDefaultPse) {
                $defaultPseData = [
                    'product_sale_element_id' => $saleElement->getId(),
                    'reference' => $saleElement->getRef(),
                    'price' => $this->formatPrice($productPrice->getPrice()),
                    'price_with_tax' => $this->formatPrice($this->computePrice($productPrice->getPrice(), 'without_tax', $object)),
                    'use_exchange_rate' => $productPrice->getFromDefaultCurrency() ? 1 : 0,
                    'currency' => $productPrice->getCurrencyId(),
                    'weight' => $saleElement->getWeight(),
                    'quantity' => $saleElement->getQuantity(),
                    'sale_price' => $this->formatPrice($productPrice->getPromoPrice()),
                    'sale_price_with_tax' => $this->formatPrice($this->computePrice($productPrice->getPromoPrice(), 'without_tax', $object)),
                    'onsale' => $saleElement->getPromo() > 0 ? 1 : 0,
                    'isnew' => $saleElement->getNewness() > 0 ? 1 : 0,
                    'isdefault' => $saleElement->getIsDefault() > 0 ? 1 : 0,
                    'ean_code' => $saleElement->getEanCode(),
                ];
            } else {
                if ($saleElement->getIsDefault()) {
                    $combinationPseData['default_pse'] = $saleElement->getId();
                    $combinationPseData['currency'] = $currentCurrency->getId();
                    $combinationPseData['use_exchange_rate'] = $productPrice->getFromDefaultCurrency() ? 1 : 0;
                }

                $this->appendValue($combinationPseData, 'product_sale_element_id', $saleElement->getId());
                $this->appendValue($combinationPseData, 'reference', $saleElement->getRef());
                $this->appendValue($combinationPseData, 'price', $this->formatPrice($productPrice->getPrice()));
                $this->appendValue($combinationPseData, 'price_with_tax', $this->formatPrice($this->computePrice($productPrice->getPrice(), 'without_tax', $object)));
                $this->appendValue($combinationPseData, 'weight', $saleElement->getWeight());
                $this->appendValue($combinationPseData, 'quantity', $saleElement->getQuantity());
                $this->appendValue($combinationPseData, 'sale_price', $this->formatPrice($productPrice->getPromoPrice()));
                $this->appendValue($combinationPseData, 'sale_price_with_tax', $this->formatPrice($this->computePrice($productPrice->getPromoPrice(), 'without_tax', $object)));
                $this->appendValue($combinationPseData, 'onsale', $saleElement->getPromo() > 0 ? 1 : 0);
                $this->appendValue($combinationPseData, 'isnew', $saleElement->getNewness() > 0 ? 1 : 0);
                $this->appendValue($combinationPseData, 'isdefault', $saleElement->getIsDefault() > 0 ? 1 : 0);
                $this->appendValue($combinationPseData, 'ean_code', $saleElement->getEanCode());
            }
        }

        $defaultPseForm = $this->createForm(AdminForm::PRODUCT_DEFAULT_SALE_ELEMENT_UPDATE, FormType::class, $defaultPseData);
        $parserContext->addForm($defaultPseForm);

        $combinationPseForm = $this->createForm(AdminForm::PRODUCT_SALE_ELEMENT_UPDATE, FormType::class, $combinationPseData);
        $parserContext->addForm($combinationPseForm);

        // Hydrate the "SEO" tab form
        $this->hydrateSeoForm($parserContext, $object);

        // The "General" tab form
        $data = [
            'id' => $object->getId(),
            'ref' => $object->getRef(),
            'locale' => $object->getLocale(),
            'title' => $object->getTitle(),
            'chapo' => $object->getChapo(),
            'description' => $object->getDescription(),
            'postscriptum' => $object->getPostscriptum(),
            'visible' => $object->getVisible(),
            'virtual' => $object->getVirtual(),
            'default_category' => $object->getDefaultCategoryId(),
            'brand_id' => $object->getBrandId(),
        ];

        // Virtual document
        if (\array_key_exists('product_sale_element_id', $defaultPseData)) {
            $virtualDocumentId = (int) MetaDataQuery::getVal('virtual', MetaData::PSE_KEY, $defaultPseData['product_sale_element_id']);
            if ($virtualDocumentId) {
                $data['virtual_document_id'] = $virtualDocumentId;
            }
        }

        // Setup the object form
        return $this->createForm(AdminForm::PRODUCT_MODIFICATION, FormType::class, $data, []);
    }

    /**
     * @param ProductEvent $event
     *
     * @return null
     */
    protected function getObjectFromEvent($event)
    {
        return $event->hasProduct() ? $event->getProduct() : null;
    }

    protected function getExistingObject()
    {
        $product = ProductQuery::create()
            ->findOneById($this->getRequest()->get('product_id', 0));

        if (null !== $product) {
            $product->setLocale($this->getCurrentEditionLocale());
        }

        return $product;
    }

    /**
     * @param Product $object
     */
    protected function getObjectLabel($object)
    {
        return $object->getTitle();
    }

    /**
     * @param Product $object
     */
    protected function getObjectId($object)
    {
        return $object->getId();
    }

    protected function getEditionArguments()
    {
        return [
                'category_id' => $this->getCategoryId(),
                'product_id' => $this->getRequest()->get('product_id', 0),
                'folder_id' => $this->getRequest()->get('folder_id', 0),
                'accessory_category_id' => $this->getRequest()->get('accessory_category_id', 0),
                'current_tab' => $this->getRequest()->get('current_tab', 'general'),
                'page' => $this->getRequest()->get('page', 1),
        ];
    }

    protected function getCategoryId()
    {
        // Trouver le category_id, soit depuis la reques, souit depuis le produit courant
        $category_id = $this->getRequest()->get('category_id', null);

        if ($category_id == null) {
            $product = $this->getExistingObject();

            if ($product !== null) {
                $category_id = $product->getDefaultCategoryId();
            }
        }

        return $category_id != null ? $category_id : 0;
    }

    protected function renderListTemplate($currentOrder)
    {
        $this->getListOrderFromSession('product', 'product_order', 'manual');

        return $this->render(
            'categories',
            [
                'product_order' => $currentOrder,
                'category_id' => $this->getCategoryId(),
                'page' => $this->getRequest()->get('page', 1),
            ]
        );
    }

    protected function redirectToListTemplate()
    {
        return $this->generateRedirectFromRoute(
            'admin.products.default',
            [
                'category_id' => $this->getCategoryId(),
                'page' => $this->getRequest()->get('page', 1),
            ]
        );
    }

    protected function renderEditionTemplate()
    {
        return $this->render('product-edit', $this->getEditionArguments());
    }

    protected function redirectToEditionTemplate()
    {
        return $this->generateRedirectFromRoute('admin.products.update', $this->getEditionArguments());
    }

    /**
     * Online status toggle product.
     */
    public function setToggleVisibilityAction(
        EventDispatcherInterface $eventDispatcher
    ) {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) {
            return $response;
        }

        $event = new ProductToggleVisibilityEvent($this->getExistingObject());

        try {
            $eventDispatcher->dispatch($event, TheliaEvents::PRODUCT_TOGGLE_VISIBILITY);
        } catch (\Exception $ex) {
            // Any error
            return $this->errorPage($ex);
        }

        // Ajax response -> no action
        return $this->nullResponse();
    }

    protected function performAdditionalDeleteAction($deleteEvent)
    {
        return $this->generateRedirectFromRoute(
            'admin.products.default',
            ['category_id' => $this->getCategoryId()]
        );
    }

    protected function performAdditionalUpdatePositionAction($positionEvent)
    {
        return $this->generateRedirectFromRoute(
            'admin.categories.default',
            ['category_id' => $this->getCategoryId()]
        );
    }

    /**
     * @param ProductUpdateEvent $updateEvent
     *
     * @return Response
     */
    protected function performAdditionalUpdateAction(EventDispatcherInterface $eventDispatcher, $updateEvent)
    {
        // Associate the file if it's a virtual product
        // and with only 1 PSE
        $virtualDocumentId = (int) $updateEvent->getVirtualDocumentId();

        if ($virtualDocumentId >= 0) {
            $defaultPSE = ProductSaleElementsQuery::create()
                ->filterByProductId($updateEvent->getProductId())
                ->filterByIsDefault(true)
                ->findOne();

            if (null !== $defaultPSE) {
                if ($virtualDocumentId !== 0) {
                    $assocEvent = new MetaDataCreateOrUpdateEvent('virtual', MetaData::PSE_KEY, $defaultPSE->getId(), $virtualDocumentId);
                    $eventDispatcher->dispatch($assocEvent, TheliaEvents::META_DATA_UPDATE);
                } else {
                    $assocEvent = new MetaDataDeleteEvent('virtual', MetaData::PSE_KEY, $defaultPSE->getId());
                    $eventDispatcher->dispatch($assocEvent, TheliaEvents::META_DATA_DELETE);
                }
            }
        }

        return null;
    }

    /**
     * return a list of document which will be displayed in AJAX.
     *
     * @return Response
     */
    public function getVirtualDocumentListAjaxAction($productId, $pseId)
    {
        $this->checkAuth(AdminResources::PRODUCT, [], AccessManager::VIEW);
        $this->checkXmlHttpRequest();

        $selectedId = (int) MetaDataQuery::getVal('virtual', MetaData::PSE_KEY, $pseId);

        $documents = ProductDocumentQuery::create()
            ->filterByProductId($productId)
            ->filterByVisible(0)
            ->orderByPosition()
            ->find()
        ;

        $results = [];

        if (null !== $documents) {
            /** @var ProductDocument $document */
            foreach ($documents as $document) {
                $results[] = [
                    'id' => $document->getId(),
                    'title' => $document->getTitle(),
                    'file' => $document->getFile(),
                    'selected' => ($document->getId() == $selectedId),
                ];
            }
        }

        return $this->jsonResponse(json_encode($results));
    }

    // -- Related content management -------------------------------------------

    public function getAvailableRelatedContentAction($productId, $folderId)
    {
        $result = [];

        $folders = FolderQuery::create()->filterById($folderId)->find();

        if ($folders !== null) {
            $list = ContentQuery::create()
                ->joinWithI18n($this->getCurrentEditionLocale())
                ->filterByFolder($folders, Criteria::IN)
                ->filterById(ProductAssociatedContentQuery::create()->filterByProductId($productId)->select('content_id')->find(), Criteria::NOT_IN)
                ->find();

            if ($list !== null) {
                /** @var Content $item */
                foreach ($list as $item) {
                    $result[] = ['id' => $item->getId(), 'title' => $item->getTitle()];
                }
            }
        }

        return $this->jsonResponse(json_encode($result));
    }

    public function addRelatedContentAction(EventDispatcherInterface $eventDispatcher)
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) {
            return $response;
        }

        $content_id = (int) $this->getRequest()->get('content_id');

        if ($content_id > 0) {
            $event = new ProductAddContentEvent(
                $this->getExistingObject(),
                $content_id
            );

            try {
                $eventDispatcher->dispatch($event, TheliaEvents::PRODUCT_ADD_CONTENT);
            } catch (\Exception $ex) {
                // Any error
                return $this->errorPage($ex);
            }
        }

        return $this->redirectToEditionTemplate();
    }

    public function deleteRelatedContentAction(EventDispatcherInterface $eventDispatcher)
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) {
            return $response;
        }

        $content_id = (int) $this->getRequest()->get('content_id');

        if ($content_id > 0) {
            $event = new ProductDeleteContentEvent(
                $this->getExistingObject(),
                $content_id
            );

            try {
                $eventDispatcher->dispatch($event, TheliaEvents::PRODUCT_REMOVE_CONTENT);
            } catch (\Exception $ex) {
                // Any error
                return $this->errorPage($ex);
            }
        }

        return $this->redirectToEditionTemplate();
    }

    // -- Accessories management ----------------------------------------------

    public function getAvailableAccessoriesAction($productId, $categoryId)
    {
        $result = [];

        $categories = CategoryQuery::create()->filterById($categoryId)->find();

        if ($categories !== null) {
            $list = ProductQuery::create()
            ->joinWithI18n($this->getCurrentEditionLocale())
            ->filterByCategory($categories, Criteria::IN)
            ->filterById(AccessoryQuery::create()->filterByProductId($productId)->select('accessory')->find(), Criteria::NOT_IN)
            ->find();

            if ($list !== null) {
                /** @var Product $item */
                foreach ($list as $item) {
                    $result[] = ['id' => $item->getId(), 'title' => $item->getTitle()];
                }
            }
        }

        return $this->jsonResponse(json_encode($result));
    }

    public function addAccessoryAction(EventDispatcherInterface $eventDispatcher)
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) {
            return $response;
        }

        $accessory_id = (int) $this->getRequest()->get('accessory_id');

        if ($accessory_id > 0) {
            $event = new ProductAddAccessoryEvent(
                $this->getExistingObject(),
                $accessory_id
            );

            try {
                $eventDispatcher->dispatch($event, TheliaEvents::PRODUCT_ADD_ACCESSORY);
            } catch (\Exception $ex) {
                // Any error
                return $this->errorPage($ex);
            }
        }

        return $this->redirectToEditionTemplate();
    }

    public function deleteAccessoryAction(EventDispatcherInterface $eventDispatcher)
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) {
            return $response;
        }

        $accessory_id = (int) $this->getRequest()->get('accessory_id');

        if ($accessory_id > 0) {
            $event = new ProductDeleteAccessoryEvent(
                $this->getExistingObject(),
                $accessory_id
            );

            try {
                $eventDispatcher->dispatch($event, TheliaEvents::PRODUCT_REMOVE_ACCESSORY);
            } catch (\Exception $ex) {
                // Any error
                return $this->errorPage($ex);
            }
        }

        return $this->redirectToEditionTemplate();
    }

    /**
     * Update accessory position.
     */
    public function updateAccessoryPositionAction(
        Request $request,
        EventDispatcherInterface $eventDispatcher
    ) {
        $accessory = AccessoryQuery::create()->findPk($request->get('accessory_id', null));

        return $this->genericUpdatePositionAction(
            $request,
            $eventDispatcher,
            $accessory,
            TheliaEvents::PRODUCT_UPDATE_ACCESSORY_POSITION
        );
    }

    /**
     * Update related content position.
     */
    public function updateContentPositionAction(
        Request $request,
        EventDispatcherInterface $eventDispatcher
    ) {
        $content = ProductAssociatedContentQuery::create()->findPk($request->get('content_id', null));

        return $this->genericUpdatePositionAction(
            $request,
            $eventDispatcher,
            $content,
            TheliaEvents::PRODUCT_UPDATE_CONTENT_POSITION
        );
    }

    /**
     * Change product template for a given product.
     *
     * @param int $productId
     *
     * @return mixed|\Symfony\Component\HttpFoundation\Response
     */
    public function setProductTemplateAction(EventDispatcherInterface $eventDispatcher, $productId)
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) {
            return $response;
        }

        $product = ProductQuery::create()->findPk($productId);

        if ($product != null) {
            $template_id = (int) $this->getRequest()->get('template_id', 0);

            $eventDispatcher->dispatch(
                new ProductSetTemplateEvent($product, $template_id, $this->getCurrentEditionCurrency()->getId()),
                TheliaEvents::PRODUCT_SET_TEMPLATE
            );
        }

        return $this->redirectToEditionTemplate();
    }

    /**
     * Update product attributes and features.
     *
     * @param int $productId
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function updateAttributesAndFeaturesAction(EventDispatcherInterface $eventDispatcher, $productId)
    {
        $product = ProductQuery::create()->findPk($productId);

        if ($product != null) {
            $featureTemplate = FeatureTemplateQuery::create()->filterByTemplateId($product->getTemplateId())->find();

            if ($featureTemplate !== null) {
                // Get all features for the template attached to this product
                $allFeatures = FeatureQuery::create()
                    ->filterByFeatureTemplate($featureTemplate)
                    ->find();

                $updatedFeatures = [];

                // Update all features values, starting with feature av. values
                $featureValues = $this->getRequest()->get('feature_value', []);

                foreach ($featureValues as $featureId => $featureValueList) {
                    // Delete all features av. for this feature.
                    $event = new FeatureProductDeleteEvent($productId, $featureId);

                    $eventDispatcher->dispatch($event, TheliaEvents::PRODUCT_FEATURE_DELETE_VALUE);

                    // Add then all selected values
                    foreach ($featureValueList as $featureValue) {
                        $event = new FeatureProductUpdateEvent($productId, $featureId, $featureValue);

                        $eventDispatcher->dispatch($event, TheliaEvents::PRODUCT_FEATURE_UPDATE_VALUE);
                    }

                    $updatedFeatures[] = $featureId;
                }

                // Update then features text values
                $featureTextValues = $this->getRequest()->get('feature_text_value', []);

                foreach ($featureTextValues as $featureId => $featureValue) {
                    // Check if a FeatureProduct exists for this product and this feature (for another lang)
                    $freeTextFeatureProduct = FeatureProductQuery::create()
                        ->filterByProductId($productId)
                        ->filterByIsFreeText(true)
                        ->findOneByFeatureId($featureId);

                    // If no corresponding FeatureProduct exists AND if the feature_text_value is empty, do nothing
                    if (null === $freeTextFeatureProduct && empty($featureValue)) {
                        continue;
                    }

                    $event = new FeatureProductUpdateEvent($productId, $featureId, $featureValue, true);
                    $event->setLocale($this->getCurrentEditionLocale());

                    $eventDispatcher->dispatch($event, TheliaEvents::PRODUCT_FEATURE_UPDATE_VALUE);

                    $updatedFeatures[] = $featureId;
                }

                // Delete features which don't have any values
                /** @var Feature $feature */
                foreach ($allFeatures as $feature) {
                    if (!\in_array($feature->getId(), $updatedFeatures)) {
                        $event = new FeatureProductDeleteEvent($productId, $feature->getId());

                        $eventDispatcher->dispatch($event, TheliaEvents::PRODUCT_FEATURE_DELETE_VALUE);
                    }
                }
            }
        }

        // If we have to stay on the same page, do not redirect to the successUrl,
        // just redirect to the edit page again.
        if ($this->getRequest()->get('save_mode') == 'stay') {
            return $this->redirectToEditionTemplate();
        }

        // Redirect to the category/product list
        return $this->redirectToListTemplate();
    }

    public function addAdditionalCategoryAction(EventDispatcherInterface $eventDispatcher)
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) {
            return $response;
        }

        $category_id = (int) $this->getRequest()->request->get('additional_category_id');

        if ($category_id > 0) {
            $event = new ProductAddCategoryEvent(
                $this->getExistingObject(),
                $category_id
            );

            try {
                $eventDispatcher->dispatch($event, TheliaEvents::PRODUCT_ADD_CATEGORY);
            } catch (\Exception $ex) {
                // Any error
                return $this->errorPage($ex);
            }
        }

        return $this->redirectToEditionTemplate();
    }

    public function deleteAdditionalCategoryAction(EventDispatcherInterface $eventDispatcher)
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) {
            return $response;
        }

        $category_id = (int) $this->getRequest()->get('additional_category_id');

        if ($category_id > 0) {
            $event = new ProductDeleteCategoryEvent(
                $this->getExistingObject(),
                $category_id
            );

            try {
                $eventDispatcher->dispatch($event, TheliaEvents::PRODUCT_REMOVE_CATEGORY);
            } catch (\Exception $ex) {
                // Any error
                return $this->errorPage($ex);
            }
        }

        return $this->redirectToEditionTemplate();
    }

    // -- Product combination management ---------------------------------------

    public function getAttributeValuesAction(/* @noinspection PhpUnusedParameterInspection */ $productId, $attributeId)
    {
        $result = [];

        // Get attribute for this product
        $attribute = AttributeQuery::create()->findPk($attributeId);

        if ($attribute !== null) {
            $values = AttributeAvQuery::create()
                ->joinWithI18n($this->getCurrentEditionLocale())
                ->filterByAttribute($attribute)
                ->find();

            if ($values !== null) {
                /** @var AttributeAv $value */
                foreach ($values as $value) {
                    $result[] = ['id' => $value->getId(), 'title' => $value->getTitle()];
                }
            }
        }

        return $this->jsonResponse(json_encode($result));
    }

    public function addAttributeValueToCombinationAction(/* @noinspection PhpUnusedParameterInspection */ $productId, $attributeAvId, $combination)
    {
        $result = [];

        // Get attribute for this product
        $attributeAv = AttributeAvQuery::create()->joinWithI18n($this->getCurrentEditionLocale())->findPk($attributeAvId);

        if ($attributeAv !== null) {
            $addIt = true;

            $attribute = AttributeQuery::create()
                ->joinWithI18n($this->getCurrentEditionLocale())
                ->findPk($attributeAv->getAttributeId());

            // Check if this attribute is not already present
            $combinationArray = explode(',', $combination);

            foreach ($combinationArray as $id) {
                $attrAv = AttributeAvQuery::create()->joinWithI18n($this->getCurrentEditionLocale())->findPk($id);

                if ($attrAv !== null) {
                    if ($attrAv->getId() == $attributeAv->getId()) {
                        $result['error'] = $this->getTranslator()->trans(
                            'A value for attribute "%name" is already present in the combination',
                            ['%name' => $attribute->getTitle().' : '.$attributeAv->getTitle()]
                        );

                        $addIt = false;
                    }

                    $subAttribute = AttributeQuery::create()
                        ->joinWithI18n($this->getCurrentEditionLocale())
                        ->findPk($attributeAv->getAttributeId());

                    $result[] = ['id' => $attrAv->getId(), 'title' => $subAttribute->getTitle().' : '.$attrAv->getTitle()];
                }
            }

            if ($addIt) {
                $result[] = ['id' => $attributeAv->getId(), 'title' => $attribute->getTitle().' : '.$attributeAv->getTitle()];
            }
        }

        return $this->jsonResponse(json_encode($result));
    }

    /**
     * A a new combination to a product.
     */
    public function addProductSaleElementAction(EventDispatcherInterface $eventDispatcher)
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) {
            return $response;
        }

        $event = new ProductSaleElementCreateEvent(
            $this->getExistingObject(),
            $this->getRequest()->get('combination_attributes', []),
            $this->getCurrentEditionCurrency()->getId()
        );

        try {
            $eventDispatcher->dispatch($event, TheliaEvents::PRODUCT_ADD_PRODUCT_SALE_ELEMENT);
        } catch (\Exception $ex) {
            // Any error
            return $this->errorPage($ex);
        }

        return $this->redirectToEditionTemplate();
    }

    /**
     * A a new combination to a product.
     */
    public function deleteProductSaleElementAction(EventDispatcherInterface $eventDispatcher)
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) {
            return $response;
        }

        $event = new ProductSaleElementDeleteEvent(
            $this->getRequest()->get('product_sale_element_id', 0),
            $this->getCurrentEditionCurrency()->getId()
        );

        try {
            $eventDispatcher->dispatch($event, TheliaEvents::PRODUCT_DELETE_PRODUCT_SALE_ELEMENT);
        } catch (\Exception $ex) {
            // Any error
            return $this->errorPage($ex);
        }

        return $this->redirectToEditionTemplate();
    }

    /**
     * Process a single PSE update, using form data array.
     *
     * @param array $data the form data
     */
    protected function processSingleProductSaleElementUpdate(EventDispatcherInterface $eventDispatcher, $data): void
    {
        $event = new ProductSaleElementUpdateEvent(
            $this->getExistingObject(),
            $data['product_sale_element_id']
        );

        $event
            ->setReference($data['reference'])
            ->setPrice($data['price'])
            ->setCurrencyId($data['currency'])
            ->setWeight($data['weight'])
            ->setQuantity($data['quantity'])
            ->setSalePrice($data['sale_price'])
            ->setOnsale($data['onsale'])
            ->setIsnew($data['isnew'])
            ->setIsdefault($data['isdefault'])
            ->setEanCode($data['ean_code'])
            ->setTaxRuleId($data['tax_rule'])
            ->setFromDefaultCurrency($data['use_exchange_rate'])
        ;

        $eventDispatcher->dispatch($event, TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT);

        // Log object modification
        if (null !== $changedObject = $event->getProductSaleElement()) {
            $this->adminLogAppend(
                $this->resourceCode,
                AccessManager::UPDATE,
                sprintf(
                    'Product Sale Element (ID %s) for product reference %s modified',
                    $changedObject->getId(),
                    $event->getProduct()->getRef()
                ),
                $changedObject->getId()
            );
        }
    }

    /**
     * Change a product sale element.
     *
     * @param BaseForm $changeForm
     *
     * @return mixed|\Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response|Response|null
     */
    protected function processProductSaleElementUpdate(EventDispatcherInterface $eventDispatcher, $changeForm)
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) {
            return $response;
        }

        try {
            // Check the form against constraints violations
            $form = $this->validateForm($changeForm, 'POST');

            // Get the form field values
            $data = $form->getData();

            if (\is_array($data['product_sale_element_id'])) {
                // Common fields
                $tmp_data = [
                    'tax_rule' => $data['tax_rule'],
                    'currency' => $data['currency'],
                    'use_exchange_rate' => $data['use_exchange_rate'],
                ];

                $count = \count($data['product_sale_element_id']);

                for ($idx = 0; $idx < $count; ++$idx) {
                    $tmp_data['product_sale_element_id'] = $pse_id = $data['product_sale_element_id'][$idx];
                    $tmp_data['reference'] = $data['reference'][$idx];
                    $tmp_data['price'] = $data['price'][$idx];
                    $tmp_data['weight'] = $data['weight'][$idx];
                    $tmp_data['quantity'] = $data['quantity'][$idx];
                    $tmp_data['sale_price'] = $data['sale_price'][$idx];
                    $tmp_data['onsale'] = isset($data['onsale'][$idx]) ? 1 : 0;
                    $tmp_data['isnew'] = isset($data['isnew'][$idx]) ? 1 : 0;
                    $tmp_data['isdefault'] = $data['default_pse'] == $pse_id;
                    $tmp_data['ean_code'] = $data['ean_code'][$idx];

                    $this->processSingleProductSaleElementUpdate($eventDispatcher, $tmp_data);
                }
            } else {
                // No need to preprocess data
                $this->processSingleProductSaleElementUpdate($eventDispatcher, $data);
            }

            // If we have to stay on the same page, do not redirect to the successUrl, just redirect to the edit page again.
            if ($this->getRequest()->get('save_mode') == 'stay') {
                return $this->redirectToEditionTemplate();
            }

            // Redirect to the success URL
            return $this->generateSuccessRedirect($changeForm);
        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        $this->setupFormErrorContext(
            $this->getTranslator()->trans('ProductSaleElement modification'),
            $error_msg,
            $changeForm,
            $ex
        );

        // At this point, the form has errors, and should be redisplayed.
        return $this->renderEditionTemplate();
    }

    /**
     * Process the change of product's PSE list.
     */
    public function updateProductSaleElementsAction(EventDispatcherInterface $eventDispatcher)
    {
        return $this->processProductSaleElementUpdate(
            $eventDispatcher,
            $this->createForm(AdminForm::PRODUCT_SALE_ELEMENT_UPDATE)
        );
    }

    /**
     * Update default product sale element (not attached to any combination).
     */
    public function updateProductDefaultSaleElementAction(EventDispatcherInterface $eventDispatcher)
    {
        return $this->processProductSaleElementUpdate(
            $eventDispatcher,
            $this->createForm(AdminForm::PRODUCT_DEFAULT_SALE_ELEMENT_UPDATE)
        );
    }

    // Create combinations
    protected function combine($input, &$output, &$tmp): void
    {
        $current = array_shift($input);

        if (\count($input) > 0) {
            foreach ($current as $element) {
                $tmp[] = $element;
                $this->combine($input, $output, $tmp);
                array_pop($tmp);
            }
        } else {
            foreach ($current as $element) {
                $tmp[] = $element;
                $output[] = $tmp;
                array_pop($tmp);
            }
        }
    }

    /**
     * Build combinations from the combination output builder.
     */
    public function buildCombinationsAction(EventDispatcherInterface $eventDispatcher)
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) {
            return $response;
        }

        $changeForm = $this->createForm(AdminForm::PRODUCT_COMBINATION_GENERATION);

        try {
            // Check the form against constraints violations
            $form = $this->validateForm($changeForm, 'POST');

            // Get the form field values
            $data = $form->getData();

            // Rework attributes_av array, to build an array which contains all combinations,
            // in the form combination[] = array of combination attributes av IDs
            //
            // First, create an array of attributes_av ID in the form $attributes_av_list[$attribute_id] = array of attributes_av ID
            // from the list of attribute_id:attributes_av ID from the form.
            $combinations = $attributes_av_list = $tmp = [];

            foreach ($data['attribute_av'] as $item) {
                [$attribute_id, $attribute_av_id] = explode(':', $item);

                if (!isset($attributes_av_list[$attribute_id])) {
                    $attributes_av_list[$attribute_id] = [];
                }

                $attributes_av_list[$attribute_id][] = $attribute_av_id;
            }

            // Next, recursively combine array
            $this->combine($attributes_av_list, $combinations, $tmp);

            // Create event
            $event = new ProductCombinationGenerationEvent(
                $this->getExistingObject(),
                $data['currency'],
                $combinations
            );

            $event
                ->setReference($data['reference'] == null ? '' : $data['reference'])
                ->setPrice($data['price'] == null ? 0 : $data['price'])
                ->setWeight($data['weight'] == null ? 0 : $data['weight'])
                ->setQuantity($data['quantity'] == null ? 0 : $data['quantity'])
                ->setSalePrice($data['sale_price'] == null ? 0 : $data['sale_price'])
                ->setOnsale($data['onsale'] == null ? false : $data['onsale'])
                ->setIsnew($data['isnew'] == null ? false : $data['isnew'])
                ->setEanCode($data['ean_code'] == null ? '' : $data['ean_code'])
            ;

            $eventDispatcher->dispatch($event, TheliaEvents::PRODUCT_COMBINATION_GENERATION);

            // Log object modification
            $this->adminLogAppend(
                $this->resourceCode,
                AccessManager::CREATE,
                sprintf(
                    'Combination generation for product reference %s',
                    $event->getProduct()->getRef()
                ),
                $event->getProduct()->getId()
            );

            // Redirect to the success URL
            return $this->generateSuccessRedirect($changeForm);
        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        $this->setupFormErrorContext(
            $this->getTranslator()->trans('Combination builder'),
            $error_msg,
            $changeForm,
            $ex
        );

        // At this point, the form has errors, and should be redisplayed.
        return $this->renderEditionTemplate();
    }

    /**
     * Invoked through Ajax; this method calculates the taxed price from the untaxed price, and vice versa.
     *
     * @since version 2.2
     */
    public function priceCalculator()
    {
        $return_price = 0;

        $price = (float) $this->getRequest()->query->get('price', 0);
        $product_id = (int) $this->getRequest()->query->get('product_id', 0);
        $action = $this->getRequest()->query->get('action', ''); // With ot without tax
        $convert = (int) $this->getRequest()->query->get('convert_from_default_currency', 0);

        if (null !== $product = ProductQuery::create()->findPk($product_id)) {
            if ($action == 'to_tax') {
                $return_price = $this->computePrice($price, 'without_tax', $product);
            } elseif ($action == 'from_tax') {
                $return_price = $this->computePrice($price, 'with_tax', $product);
            } else {
                $return_price = $price;
            }

            if ($convert != 0) {
                $return_price = $price * Currency::getDefaultCurrency()->getRate();
            }
        }

        return new JsonResponse(['result' => $this->formatPrice($return_price)]);
    }

    /**
     * Calculate tax or untax price for a non existing product.
     *
     * For an existing product, use self::priceCaclulator
     *
     * @return JsonResponse
     */
    public function calculatePrice()
    {
        $return_price = 0;

        $price = (float) $this->getRequest()->query->get('price');
        $tax_rule_id = (int) $this->getRequest()->query->get('tax_rule');
        $action = $this->getRequest()->query->get('action'); // With ot without tax

        $taxRule = TaxRuleQuery::create()->findPk($tax_rule_id);

        if (null !== $price && null !== $taxRule) {
            $calculator = new Calculator();

            $calculator->loadTaxRuleWithoutProduct(
                $taxRule,
                Country::getShopLocation()
            );

            if ($action == 'to_tax') {
                $return_price = $calculator->getTaxedPrice($price);
            } elseif ($action == 'from_tax') {
                $return_price = $calculator->getUntaxedPrice($price);
            } else {
                $return_price = $price;
            }
        }

        return new JsonResponse(['result' => $this->formatPrice($return_price)]);
    }

    /**
     * Calculate all prices.
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function loadConvertedPrices()
    {
        $product_sale_element_id = (int) $this->getRequest()->get('product_sale_element_id', 0);
        $currency_id = (int) $this->getRequest()->get('currency_id', 0);

        $price_with_tax = $price_without_tax = $sale_price_with_tax = $sale_price_without_tax = 0;

        if (null !== $pse = ProductSaleElementsQuery::create()->findPk($product_sale_element_id)) {
            if ($currency_id > 0
                &&
                $currency_id != Currency::getDefaultCurrency()->getId()
                &&
                null !== $currency = CurrencyQuery::create()->findPk($currency_id)) {
                // Get the default currency price
                $productPrice = ProductPriceQuery::create()
                    ->filterByCurrency(Currency::getDefaultCurrency())
                    ->filterByProductSaleElementsId($product_sale_element_id)
                    ->findOne()
                ;

                // Calculate the converted price
                if (null !== $productPrice) {
                    $price_without_tax = $productPrice->getPrice() * $currency->getRate();
                    $sale_price_without_tax = $productPrice->getPromoPrice() * $currency->getRate();
                }
            }

            if (null !== $product = $pse->getProduct()) {
                $price_with_tax = $this->computePrice($price_without_tax, 'with_tax', $product);
                $sale_price_with_tax = $this->computePrice($sale_price_without_tax, 'with_tax', $product);
            }
        }

        return new JsonResponse([
            'price_with_tax' => $this->formatPrice($price_with_tax),
            'price_without_tax' => $this->formatPrice($price_without_tax),
            'sale_price_with_tax' => $this->formatPrice($sale_price_with_tax),
            'sale_price_without_tax' => $this->formatPrice($sale_price_without_tax),
        ]);
    }

    /**
     * Calculate taxed/untexted price for a product.
     *
     * @param bool $convert
     *
     * @return string
     */
    protected function computePrice($price, $price_type, Product $product, $convert = false)
    {
        $calc = new Calculator();

        $calc->load(
            $product,
            Country::getShopLocation()
        );

        if ($price_type == 'without_tax') {
            $return_price = $calc->getTaxedPrice($price);
        } elseif ($price_type == 'with_tax') {
            $return_price = $calc->getUntaxedPrice($price);
        } else {
            $return_price = $price;
        }

        if ($convert != 0) {
            $return_price = $price * Currency::getDefaultCurrency()->getRate();
        }

        return (float) $return_price;
    }

    /**
     * @param int    $pseId
     * @param string $type
     * @param int    $typeId
     *
     * @return mixed|\Thelia\Core\HttpFoundation\Response
     */
    public function productSaleElementsProductImageDocumentAssociation(EventDispatcherInterface $eventDispatcher, $pseId, $type, $typeId)
    {
        /*
         * Check user's auth
         */
        if (null !== $response = $this->checkAuth(AdminResources::PRODUCT, [], AccessManager::UPDATE)) {
            return $response;
        }

        $this->checkXmlHttpRequest();

        /**
         * Check given type.
         */
        $responseData = [];

        try {
            $responseData = $this->getAssociationResponseData($eventDispatcher, $pseId, $type, $typeId);
        } catch (\Exception $e) {
            $responseData['error'] = $e->getMessage();
        }

        return new JsonResponse($responseData, isset($responseData['error']) ? 500 : 200);
    }

    public function getAssociationResponseData(EventDispatcherInterface $eventDispatcher, $pseId, $type, $typeId)
    {
        $responseData = [];

        if (null !== $msg = $this->checkFileType($type)) {
            throw new \Exception($msg);
        }

        $responseData['product_sale_elements_id'] = $pseId;

        $pse = ProductSaleElementsQuery::create()->findPk($pseId);

        if (null === $pse) {
            throw new \Exception(
                $this->getTranslator()->trans(
                    "The product sale elements id %id doesn't exists",
                    [
                        '%id' => $pseId,
                    ]
                )
            );
        }

        $assoc = null;

        if ($type === 'image') {
            $image = ProductImageQuery::create()->findPk($typeId);

            if (null === $image) {
                throw new \Exception(
                    $this->getTranslator()->trans(
                        "The product image id %id doesn't exists",
                        [
                            '%id' => $typeId,
                        ]
                    )
                );
            }

            $assoc = ProductSaleElementsProductImageQuery::create()
                ->filterByProductSaleElementsId($pseId)
                ->findOneByProductImageId($typeId)
            ;

            if (null === $assoc) {
                $assoc = new ProductSaleElementsProductImage();

                $assoc
                    ->setProductSaleElementsId($pseId)
                    ->setProductImageId($typeId)
                    ->save()
                ;
            } else {
                $assoc->delete();
            }

            $responseData['product_image_id'] = $typeId;
            $responseData['is-associated'] = (int) (!$assoc->isDeleted());
        } elseif ($type === 'document') {
            $image = ProductDocumentQuery::create()->findPk($typeId);

            if (null === $image) {
                throw new \Exception(
                    $this->getTranslator()->trans(
                        "The product document id %id doesn't exists",
                        [
                            '%id' => $pseId,
                        ]
                    )
                );
            }

            $assoc = ProductSaleElementsProductDocumentQuery::create()
                ->filterByProductSaleElementsId($pseId)
                ->findOneByProductDocumentId($typeId)
            ;

            if (null === $assoc) {
                $assoc = new ProductSaleElementsProductDocument();

                $assoc
                    ->setProductSaleElementsId($pseId)
                    ->setProductDocumentId($typeId)
                    ->save()
                ;
            } else {
                $assoc->delete();
            }

            $responseData['product_document_id'] = $typeId;
            $responseData['is-associated'] = (int) (!$assoc->isDeleted());
        } elseif ($type === 'virtual') {
            $image = ProductDocumentQuery::create()->findPk($typeId);

            if (null === $image) {
                throw new \Exception(
                    $this->getTranslator()->trans(
                        "The product document id %id doesn't exists",
                        [
                            '%id' => $pseId,
                        ]
                    )
                );
            }

            $documentId = (int) MetaDataQuery::getVal('virtual', MetaData::PSE_KEY, $pseId);

            if ($documentId === (int) $typeId) {
                $assocEvent = new MetaDataDeleteEvent('virtual', MetaData::PSE_KEY, $pseId);
                $eventDispatcher->dispatch($assocEvent, TheliaEvents::META_DATA_DELETE);
                $responseData['is-associated'] = 0;
            } else {
                $assocEvent = new MetaDataCreateOrUpdateEvent('virtual', MetaData::PSE_KEY, $pseId, $typeId);
                $eventDispatcher->dispatch($assocEvent, TheliaEvents::META_DATA_UPDATE);
                $responseData['is-associated'] = 1;
            }

            $responseData['product_document_id'] = $typeId;
        }

        return $responseData;
    }

    public function checkFileType($type)
    {
        $types = ['image', 'document', 'virtual'];

        if (!\in_array($type, $types)) {
            return $this->getTranslator()->trans(
                'The type %type is not valid',
                [
                    '%type' => $type,
                ]
            );
        }

        return null;
    }

    public function getAjaxProductSaleElementsImagesDocuments(EventDispatcherInterface $eventDispatcher, $id, $type)
    {
        if (null !== $this->checkAuth(AdminResources::PRODUCT, [], AccessManager::VIEW)) {
            return JsonResponse::createAuthError(AccessManager::VIEW);
        }

        $this->checkXmlHttpRequest();

        $pse = ProductSaleElementsQuery::create()
            ->findPk($id);

        $errorMessage = $this->checkFileType($type);

        if (null === $pse && null === $errorMessage) {
            $type = null;

            $errorMessage = $this->getTranslator()->trans(
                "The product sale elements id %id doesn't exist",
                [
                    '%id' => $pse->getId(),
                ]
            );
        }

        switch ($type) {
            case 'image':
                $modalTitle = $this->getTranslator()->trans('Associate images');
                $data = $this->getPSEImages($eventDispatcher, $pse);
                break;
            case 'document':
                $modalTitle = $this->getTranslator()->trans('Associate documents');
                $data = $this->getPSEDocuments($eventDispatcher, $pse);
                break;
            case 'virtual':
                $modalTitle = $this->getTranslator()->trans('Select the virtual document');
                $data = $this->getPSEVirtualDocument($eventDispatcher, $pse);
                break;
            case null:
            default:
                $modalTitle = $this->getTranslator()->trans('Unsupported type');
                $data = [];
        }

        if (empty($data) && null === $errorMessage) {
            $errorMessage = $this->getTranslator()->trans('There are no files to associate.');
            if ($type === 'virtual') {
                $errorMessage .= $this->getTranslator()->trans(' note: only non-visible documents can be associated.');
            }
        }

        $this->getParserContext()
            ->set('items', $data)
            ->set('type', $type)
            ->set('error_message', $errorMessage)
            ->set('modal_title', $modalTitle)
        ;

        return $this->render('ajax/pse-image-document-assoc-modal');
    }

    protected function getPSEImages(EventDispatcherInterface $eventDispatcher, ProductSaleElementsModel $pse)
    {
        /** @var Image $imageLoop */
        $imageLoop = $this->createLoopInstance($eventDispatcher, Image::class);

        $imageLoop->initializeArgs([
            'product' => $pse->getProductId(),
            'width' => 100,
            'height' => 75,
            'resize_mode' => 'borders',
        ]);

        $images = $imageLoop
            ->exec($imagePagination)
        ;

        $imageAssoc = ProductSaleElementsProductImageQuery::create()
            ->filterByProductSaleElementsId($pse->getId())
            ->find()
            ->toArray()
        ;

        $data = [];

        /* @var \Thelia\Core\Template\Element\LoopResultRow $image */
        for ($images->rewind(); $images->valid(); $images->next()) {
            $image = $images->current();

            $isAssociated = $this->arrayHasEntries($imageAssoc, [
                'ProductImageId' => $image->get('ID'),
                'ProductSaleElementsId' => $pse->getId(),
            ]);

            $data[] = [
                'id' => $image->get('ID'),
                'url' => $image->get('IMAGE_URL'),
                'title' => $image->get('TITLE'),
                'is_associated' => $isAssociated,
                'filename' => $image->model->getFile(),
            ];
        }

        return $data;
    }

    protected function getPSEDocuments(EventDispatcherInterface $eventDispatcher, ProductSaleElementsModel $pse)
    {
        /** @var Document $documentLoop */
        $documentLoop = $this->createLoopInstance($eventDispatcher, Document::class);

        $documentLoop->initializeArgs([
            'product' => $pse->getProductId(),
            'visible' => BooleanOrBothType::ANY, // Do not restrict on visibility for single association
        ]);

        $documents = $documentLoop
            ->exec($documentPagination)
        ;

        $documentAssoc = ProductSaleElementsProductDocumentQuery::create()
            ->useProductSaleElementsQuery()
                ->filterById($pse->getId())
            ->endUse()
            ->find()
            ->toArray()
        ;

        $data = [];

        /* @var \Thelia\Core\Template\Element\LoopResultRow $document */
        for ($documents->rewind(); $documents->valid(); $documents->next()) {
            $document = $documents->current();

            $isAssociated = $this->arrayHasEntries($documentAssoc, [
                'ProductDocumentId' => $document->get('ID'),
                'ProductSaleElementsId' => $pse->getId(),
            ]);

            $data[] = [
                'id' => $document->get('ID'),
                'url' => $document->get('DOCUMENT_URL'),
                'title' => $document->get('TITLE'),
                'is_associated' => $isAssociated,
                'filename' => $document->model->getFile(),
            ];
        }

        return $data;
    }

    protected function getPSEVirtualDocument(EventDispatcherInterface $eventDispatcher, ProductSaleElementsModel $pse)
    {
        /** @var Document $documentLoop */
        $documentLoop = $this->createLoopInstance($eventDispatcher, Document::class);

        // select only not visible documents
        $documentLoop->initializeArgs([
            'product' => $pse->getProductId(),
            'visible' => 0,
        ]);

        $documents = $documentLoop
            ->exec($documentPagination)
        ;

        $documentId = (int) MetaDataQuery::getVal('virtual', 'pse', $pse->getId());

        $data = [];

        /* @var \Thelia\Core\Template\Element\LoopResultRow $document */
        for ($documents->rewind(); $documents->valid(); $documents->next()) {
            $document = $documents->current();

            $data[] = [
                'id' => $document->get('ID'),
                'url' => $document->get('DOCUMENT_URL'),
                'title' => $document->get('TITLE'),
                'is_associated' => ($documentId === $document->get('ID')),
                'filename' => $document->model->getFile(),
            ];
        }

        return $data;
    }

    /**
     * Todo refactor this to not use container or not use loop at all
     * Compute images with the associated loop.
     */
    protected function createLoopInstance(EventDispatcherInterface $eventDispatcher, $loopClass)
    {
        return new $loopClass(
            $this->container,
            $this->container->get('request_stack'),
            $eventDispatcher,
            $this->getSecurityContext(),
            $this->getTranslator(),
            $this->container->getParameter('Thelia.parser.loops'),
            $this->container->getParameter('kernel.environment')
        );
    }

    protected function arrayHasEntries(array $data, array $entries)
    {
        $status = false;
        $countEntries = \count($entries);

        foreach ($data as &$line) {
            $localMatch = 0;

            foreach ($entries as $key => $entry) {
                if (isset($line[$key]) && $line[$key] === $entry) {
                    ++$localMatch;
                }
            }

            if ($localMatch === $countEntries) {
                $status = true;
                unset($line);
                break;
            }
        }

        return $status;
    }

    /**
     * @throws \Exception
     *
     * @return mixed|\Symfony\Component\HttpFoundation\Response
     */
    public function cloneAction(EventDispatcherInterface $eventDispatcher)
    {
        if (null !== $response = $this->checkAuth($this->resourceCode, $this->getModuleCode(), [AccessManager::CREATE, AccessManager::UPDATE])) {
            return $response;
        }

        // Initialize vars
        $cloneProductForm = $this->createForm(AdminForm::PRODUCT_CLONE);
        $lang = $this->getSession()->getLang()->getLocale();

        try {
            // Check the form against constraints violations
            $form = $this->validateForm($cloneProductForm, 'POST');

            $originalProduct = ProductQuery::create()
                ->findPk($form->getData()['productId']);

            // Build and dispatch product clone event
            $productCloneEvent = new ProductCloneEvent(
                $form->getData()['newRef'],
                $lang,
                $originalProduct
            );
            $eventDispatcher->dispatch($productCloneEvent, TheliaEvents::PRODUCT_CLONE);

            return $this->generateRedirectFromRoute(
                'admin.products.update',
                ['product_id' => $productCloneEvent->getClonedProduct()->getId()]
            );
        } catch (FormValidationException $e) {
            $this->setupFormErrorContext(
                $this->getTranslator()->trans('Product clone'),
                $e->getMessage(),
                $cloneProductForm,
                $e
            );

            return $this->redirectToEditionTemplate();
        }
    }

    /**
     * @param string $price
     *
     * @return float
     */
    protected function formatPrice($price)
    {
        return (float) number_format($price, 6, '.', '');
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return mixed|\Thelia\Core\HttpFoundation\Response
     */
    public function searchCategoryAction()
    {
        $search = '%'.$this->getRequest()->query->get('q').'%';

        $resultArray = [];

        $categoriesI18n = CategoryI18nQuery::create()->filterByTitle($search, Criteria::LIKE)->limit(100);

        /** @var \Thelia\Model\CategoryI18n $categoryI18n */
        foreach ($categoriesI18n as $categoryI18n) {
            $category = $categoryI18n->getCategory();
            $resultArray[$category->getId()] = $categoryI18n->getTitle();
        }

        return $this->jsonResponse(json_encode($resultArray));
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return mixed|\Thelia\Core\HttpFoundation\Response
     */
    public function searchProductAction()
    {
        $search = '%'.$this->getRequest()->query->get('q').'%';

        $resultArray = [];

        $productsI18nQuery = ProductI18nQuery::create()->filterByTitle($search, Criteria::LIKE);

        $category_id = $this->getRequest()->query->get('category_id');
        if ($category_id != null) {
            $productsI18nQuery
                ->useProductQuery()
                ->useProductCategoryQuery()
                ->filterByCategoryId($category_id)
                ->endUse()
                ->endUse();
        }

        $products = $productsI18nQuery->limit(100);

        /** @var \Thelia\Model\ProductI18n $product */
        foreach ($products as $product) {
            $resultArray[$product->getId()] = $product->getTitle();
        }

        return $this->jsonResponse(json_encode($resultArray));
    }
}
