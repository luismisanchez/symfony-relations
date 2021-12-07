<?php

namespace App\Controller\Admin;

use App\Entity\Actor;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;

class ActorsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Actor::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index','Actors');
    }

    public function configureFields(string $pageName): iterable
    {
        $fields = [
            'id',
            'name',
            AssociationField::new('films')->setTemplatePath('admin/field/films.html.twig'),
            'birthdate',
            'born',
        ];

        if ($pageName === 'edit') {
            $fields = [
                Field::new('id')->setDisabled(true),
                'name',
                'birthdate',
                'born',
            ];
        }

        return $fields;
    }

}
