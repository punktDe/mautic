Neos Mautic Plug-in
====================

This Plug-in enables the usage of Marketing Automation Tool Mautic together with Neos CMS. It's key features are

- Add Mautic Tracking in two easy steps
- Data Collection via Neos Forms & Neos FormBuilder Forms
- SEO friendly dynamic content

# Installation
Add in composer.json (or expand your `repositories` if already existing)

    "repositories": [
        {"type": "git", "url": "git@github.com:punktDe/Mautic.git"}
      ],
    
 
Add in composer.json in your requirements:

    "punktde/mautic": "@dev"
    
Execute `composer update` in Project. 

This is going to change, when this package is represented in packagist. 

# Configuration

### Configure Mautic

- Visit your Mautic installation and [create a user for API](https://mautic.com/help/users-and-roles/). 
- [Enable API and HTTP basic auth](https://mautic.com/help/api-quick-start/). Optional: Be sure your Mautic installation 
is running on HTTPS for the sake of security.
- Skip this, if your Website and Mautic are running on the same server:
    - [Enable CORS](https://mautic.com/help/getting-started-mautic-cloud/#4), enter your site in `valid domains`.

### Configure Plug-in

Enter following Configuration in your Site Settings.yaml

    PunktDe:
      Mautic:
        mauticServer:
          url: https://mymautic.com
        mauticUser:
          username: mautic-api
          password: 
          
# Enable Tracking

- Be sure you entered the correct Mautic URL in your configuration.
- Place Mautic Tracking Template at the bottom of your `<body>`:


        mautic = PunktDe.Mautic:MauticTracking
    
    
Add this code to every page fusion file you want them to be tracked. If you want all pages being tracked, add this 
piece to `Root.fusion` or `AbstractPage.fusion` of your page.

# Pass Information from Form to Mautic

### Form in yaml format

To pass information from yaml forms to mautic, you need to define which form elements should be taken into account.
Add following property:   

    mauticIdentifier: 'firstname'


You also need to add following finisher to the form:

    identifier: 'PunktDe.Mautic:UpdateUser'
    

An example form looks like this:

    type: 'Neos.Form:Form'
    identifier: 'form-identifier'
    label: 'Blog comment'
    renderingOptions:
      submitButtonLabel: 'Send'
    renderables:
      -
        type: 'Neos.Form:Page'
        identifier: 'blog-comment'
        renderables:
          -
            type: 'Neos.Form:SingleLineText'
            identifier: 'name'
            label: 'Name'
            properties:
              mauticIdentifier: 'firstname'
            defaultValue: ''
    finishers:
      -
        identifier: 'PunktDe.Mautic:UpdateUser'

The value of `mauticIdentifier` must be a defined user field (so called custom field) in Mautic. You can search, edit or create new
[custom fields](https://www.mautic.org/docs/en/contacts/manage_fields.html) to fit to your needs. 


### Form in FormBuilder Format

Forms created by FormBuilder requires the editors to enter Mautic Identifier in Neos Backend. This can be achieved by selecting
the form element and setting up the value on the settings bar right side of your UI.

You must also register the Mautic Finisher for this form. 

![](ReadmeFiles/register-Finisher.gif)


# Dynamic Content

You can show a different content dimension to different Mautic Segments.

- [Create a new segment in Mautic](https://mautic.com/help/segments/).
- Define segments in your `settings.yaml`. Name your neos dimensions same as your mautic segments.


    Neos:
      ContentRepository:
        contentDimensions:
          mautic:
            label: 'Mautic Segment'
            icon: 'icon-globe'
            default: defaultUser
            defaultPreset: defaultUser
            presets:
              all: null
              defaultUser:
                label: 'Default User'
                values:
                  - defaultUser
                uriSegment: ''
              twitter:
                label: twitter
                values:
                  - twitter
                uriSegment: twitter

- You can combine multiple dimensions (f.e. language) without problem.
- Visit backend, change the dimension and edit content. Publish it.  

![](ReadmeFiles/changeDimension.gif)
