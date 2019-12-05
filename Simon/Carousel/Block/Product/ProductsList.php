<?php
namespace Simon\Carousel\Block\Product;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;

class ProductsList extends Template implements BlockInterface
{
    const DEFAULT_SORT_BY = 'id';
    const DEFAULT_SORT_ORDER = 'asc';
    const PAGE_SIZE = 5;
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Catalog\Helper\Category $category,
        \Magento\Catalog\Model\CategoryRepository $categoryRepository,
        \Magento\Widget\Helper\Conditions $conditionsHelper,
        \Magento\Framework\View\Asset\Repository $assetRepos,
        \Magento\Catalog\Helper\ImageFactory $helperImageFactory,
        \Magento\Framework\Image\AdapterFactory $imageFactory,
        \Magento\Framework\Filesystem $filesystem,
        array $data = []
    ) {
        $this->storeManager = $context->getStoreManager();
        $this->category = $category;
        $this->categoryRepository = $categoryRepository;
        $this->conditionsHelper = $conditionsHelper;
        $this->assetRepos = $assetRepos;
        $this->helperImageFactory = $helperImageFactory;
        $this->imageFactory = $imageFactory;
        $this->_filesystem = $filesystem;
        $this->_directory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        parent::__construct(
            $context,
            $data
        );
    }

    public function createCollection()
 {
     $collection = $this->productCollectionFactory->create();
     $collection->setVisibility($this->catalogProductVisibility->getVisibleInCatalogIds());

     $collection = $this->_addProductAttributesAndPrices($collection)
         ->addStoreFilter()
         ->setPageSize($this->getPageSize())
         ->setCurPage($this->getRequest()->getParam($this->getData('page_var_name'), 1))
         ->setOrder($this->getSortBy(), $this->getSortOrder());

     $conditions = $this->getConditions();
     $conditions->collectValidatedAttributes($collection);
     $this->sqlBuilder->attachConditionToCollection($collection, $conditions);

     return $collection;
 }

    /**
     * Get small place holder image
     *
     * @return string
     */
    public function getPlaceHolderImage()
    {
        /** @var \Magento\Catalog\Helper\Image $helper */
        $imagePlaceholder = $this->helperImageFactory->create();
        return $this->assetRepos->getUrl($imagePlaceholder->getPlaceholder('small_image'));
    }
    /**
     * @param array $categoryIds
     * @return \Magento\Framework\Data\Collection
     */
    public function getCategory()
    {
        $categoryIds = $this->getCategoryIds();
        if (!empty($categoryIds)) {
            $ids = array_slice(explode(',', $categoryIds), 0, $this->getCategoryToDisplay());
            $category = [];
            foreach ($ids as $id) {
                $category[] = $this->categoryRepository->get($id);
            }
            return $category;
        }
        return '';
    }
    /**
     * @param $imageName string imagename only
     * @param $width
     * @param $height
     */
    public function getResize($imageName, $width = 258, $height = 200)
    {
        $realPath = $this->_filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath('catalog/category/' . $imageName);
        if (!$this->_directory->isFile($realPath) || !$this->_directory->isExist($realPath)) {
            return false;
        }
        $targetDir = $this->_filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath('resized/' . $width . 'x' . $height);
        $pathTargetDir = $this->_directory->getRelativePath($targetDir);
        if (!$this->_directory->isExist($pathTargetDir)) {
            $this->_directory->create($pathTargetDir);
        }
        if (!$this->_directory->isExist($pathTargetDir)) {
            return false;
        }
        $image = $this->imageFactory->create();
        $image->open($realPath);
        $image->keepAspectRatio(true);
        $image->resize($width, $height);
        $dest = $targetDir . '/' . pathinfo($realPath, PATHINFO_BASENAME);
        $image->save($dest);
        if ($this->_directory->isFile($this->_directory->getRelativePath($dest))) {
            return $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'resized/' . $width . 'x' . $height . '/' . $imageName;
        }
        return false;
    }
    /**
     * Retrieve how many category should be displayed
     *
     * @return int
     */
    public function getCategoryToDisplay()
    {
        if (!$this->hasData('page_size')) {
            $this->setData('page_size', self::PAGE_SIZE);
        }
        return $this->getData('page_size');
    }
    /**
     * Retrieve category ids from widget
     *
     * @return string
     */
    public function getCategoryIds()
    {
        $conditions = $this->getData('conditions')
            ? $this->getData('conditions')
            : $this->getData('conditions_encoded');
        if ($conditions) {
            $conditions = $this->conditionsHelper->decode($conditions);
        }
        foreach ($conditions as $key => $condition) {
            if (!empty($condition['attribute']) && $condition['attribute'] == 'category_ids') {
                return $condition['value'];
            }
        }
        return '';
    }
    public function getSortBy()
    {
        if (!$this->hasData('products_sort_by')) {
            $this->setData('products_sort_by', self::DEFAULT_SORT_BY);
        }
        return $this->getData('products_sort_by');
    }
    public function getSortOrder()
    {
        if (!$this->hasData('products_sort_order')) {
            $this->setData('products_sort_order', self::DEFAULT_SORT_ORDER);
        }
        return $this->getData('products_sort_order');
    }
}
